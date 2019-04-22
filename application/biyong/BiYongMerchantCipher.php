<?php
/*
 *  64位系统可直接运行
 *
 *  32位系统 int为32bit，需要需要修改以下函数实现才可运行
 *    _timestampLong()
 *    _longToBytes()
 *    _bytesToLong()
*/

include_once('Crypt/RSA.php');


class BiYongMerchantCipher {
  var $privateKey;
  var $publicKey;
  var $shaHashMode;
  var $aesMode;
  var $aesMethod;
  var $aesOptions;
  var $httpHeaders;

  function __construct($appId, $privateKey, $publicKey, $shaHashMode='SHA256', $aesMode=null) {
    if (!$appId) {
      throw new EncryptException("appId 不能为空");
    }
    if (!$privateKey) {
      throw new EncryptException("privateKey 不能为空");
    }
    if (!$publicKey) {
      throw new EncryptException("publicKey 不能为空");
    }

  	$this->privateKey = base64_decode($privateKey);
  	$this->publicKey = base64_decode($publicKey);
  	// $this->publicKey = "-----BEGIN PUBLIC KEY-----\r\n" . chunk_split($publicKey) . "-----END PUBLIC KEY-----";
  	$this->shaHashMode = strtolower($shaHashMode);
  	// 设置请求头
  	$this->httpHeaders = array(
      "Content-Type: application/json",
      "AppId: $appId",
      "MerchantClient: php-1.0.0",
      "RsaSignHashMode: " . strtoupper($shaHashMode),
    );
    if ($aesMode) {
      $this->aesMode = $aesMode;
      switch ($aesMode) {
        case "CBC/PKCS5Padding":
        case "CFB/NoPadding":
        case "CFB/PKCS5Padding":
        case "CTR/NoPadding":
        case "CTR/PKCS5Padding":
        case "OFB/NoPadding":
          break;
        default:
          throw new EncryptException("不支持此AES加密:$aesMode");
      }
      list($aesMethod, $padding) = explode('/', $aesMode);
  	  $this->aesMethod = "AES-128-" . $aesMethod;
  	  $this->aesOptions = ($padding == "NoPadding") ? (OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING) : OPENSSL_RAW_DATA;
      array_push($this->httpHeaders, "AesEncryptMode: " . $aesMode);
    }
  }

  function clientEncrypt($dataString) {
    $m = new ByMessage();
    $messageId = $this->_newMessageId();
    $timestamp = $this->_timestampLong();
    $data = $this->_longToBytes($timestamp) . $messageId . $dataString;
    $sign = $this->_rsaSign($data, $this->privateKey);
    $signedData = $this->_intToBytes(strlen($sign)) . $sign . $data;
    if ($this->aesMode) {
      $aesKey = substr($sign, 0, 16);
      $aesIv = substr($sign, 16, 16);
      $m->data = $this->_clientAesEncrypt($signedData, $aesKey, $aesIv);
      $m->aesMode = $this->aesMode;
      $m->aesKey = $aesKey;
      $m->aesIv = $aesIv;
    } else {
      $m->data = $signedData;
    }
    $m->messageId = $messageId;
    $m->timestamp = $timestamp;
    return $m;
  }

  function clientDecrypt($data, $message) {
    if ($data[0] != 0) {
      // 直接返回异常信息
      return $data;
    }
    if ($message->aesMode) {
      $data = substr($data, 1, strlen($data) - 1);
      $data = $this->_aesDecrypt($data, $message->aesKey, $message->aesIv);
    }
    $signLen = $this->_bytesToInt(substr($data, 0, 4));
    $sign = substr($data, 4, $signLen);
    $data = substr($data, 4 + $signLen, strlen($data) - 4 - $signLen);
    if ($this->_rsaVerify($data, $sign, $this->publicKey)) {
      if (substr($data, 0, 16) == $message->messageId) {
        return substr($data, 16, strlen($data) - 16);
      } else {
        throw new EncryptException("messageId error");
      }
    } else {
      throw new EncryptException("sign error");
    }
  }

  function serverEncrypt($data, $message) {
    $data = $message->messageId . $data;
    $sign = $this->_rsaSign($data, $this->privateKey);
    $signedData = $this->_intToBytes(strlen($sign)) . $sign . $data;
    if ($message->aesMode) {
      $aesEncryptedData = $this->_aesEncrypt($signedData, $message->aesKey, $message->aesIv);
      return chr(0) . $aesEncryptedData;
    } else {
      return $signedData;
    }
  }

  function serverDecrypt($data) {
    $m = new ByMessage();
    if ($this->aesMode) {
      $encryptAesKvLen = $this->_bytesToInt(substr($data, 0, 4));
      $keyIv = $this->_rsaDecrypt(substr($data, 4, $encryptAesKvLen), $this->privateKey);
      $aesEncryptData = substr($data, 4 + $encryptAesKvLen, strlen($data) - 4 - $encryptAesKvLen);
      $aesKey = substr($keyIv, 0, 16);
      $aesIv = substr($keyIv, 16, 16);
      $data = $this->_aesDecrypt($aesEncryptData, $aesKey, $aesIv);
      $m->aesMode = $this->aesMode;
      $m->aesKey = $aesKey;
      $m->aesIv = $aesIv;
    }
    $signLen = $this->_bytesToInt(substr($data, 0, 4));
    $_data = substr($data, 4 + $signLen, strlen($data) - 4 - $signLen);
    if ($this->_rsaVerify($_data, substr($data, 4, $signLen), $this->publicKey)) {
      $m->timestamp = $this->_bytesToLong(substr($_data, 0, 8));
      $m->messageId = substr($_data, 8, 16);
      $m->data = substr($_data, 24, strlen($_data) - 24);
      return $m;
    } else {
      throw new EncryptException("sign error");
    }
  }

  function _clientAesEncrypt($data, $key, $iv) {
    $aesEncryptedData = $this->_aesEncrypt($data, $key, $iv);
    $rsaEncryptedAesKeyIv = $this->_rsaEncrypt($key . $iv, $this->publicKey);
    return $this->_intToBytes(strlen($rsaEncryptedAesKeyIv)) . $rsaEncryptedAesKeyIv . $aesEncryptedData;
  }


  function _aesEncrypt($data, $key, $iv) {
    $encryptedData = openssl_encrypt($data, $this->aesMethod, $key, $this->aesOptions, $iv);
    if ($encryptedData) {
      return $encryptedData;
    } else {
      throw new EncryptException("aes encrypt " . openssl_error_string());
    }
  }

  function _aesDecrypt($data, $key, $iv) {
    $decryptedData = openssl_decrypt($data, $this->aesMethod, $key, $this->aesOptions, $iv);
    if ($decryptedData) {
      return $decryptedData;
      return $this->aesPkcs5pad ? $this->_pkcs5Unpad($decryptedData) : $decryptedData;
    } else {
      throw new EncryptException("aes decrypt " . openssl_error_string());
    }
  }

  function _timestampLong() {
    return (int) (microtime(true) * 1000);
  }
  function _longToBytes($l) {
    return pack("NN", $l >> 32, $l & 0xFFFFFFFF);
  }
  function _bytesToLong($b) {
    return (ord($b[0])<<56) + (ord($b[1])<<48) + (ord($b[2])<<40) + (ord($b[3])<<32) +
           (ord($b[4])<<24) + (ord($b[5])<<16) + (ord($b[6])<<8) + ord($b[7]);
  }

  function _intToBytes($i) {
    return pack("N", $i);
  }
  function _bytesToInt($b) {
    return (ord($b[0])<<24) + (ord($b[1])<<16) + (ord($b[2])<<8) + ord($b[3]);
  }

  function _newMessageId() {
    return openssl_random_pseudo_bytes(16);
  }

  function _rsaSign($data, $key) {
    $rsa = new Crypt_RSA();
    if (!$rsa->loadKey($key)) {
      throw new EncryptException("rsa sign load key error");
    }
    $rsa->setHash($this->shaHashMode);
    $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
    return $rsa->sign($data);
  }

  function _rsaVerify($data, $signature, $key) {
    $rsa = new Crypt_RSA();
    if (!$rsa->loadKey($key)) {
      throw new EncryptException("rsa verify load key error");
    }
    $rsa->setHash($this->shaHashMode);
    $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
    return $rsa->verify($data, $signature);
  }

  function _rsaEncrypt($data, $key) {
    $rsa = new Crypt_RSA();
    if (!$rsa->loadKey($key)) {
      throw new EncryptException("rsa encrypt load key error");
    }
    $rsa->setEncryptionMode(CRYPT_RSA_SIGNATURE_PKCS1);
    return $rsa->encrypt($data);
  }

  function _rsaDecrypt($data, $key) {
    $rsa = new Crypt_RSA();
    if (!$rsa->loadKey($key)) {
      throw new EncryptException("rsa decrypt load key error");
    }
    $rsa->setEncryptionMode(CRYPT_RSA_SIGNATURE_PKCS1);
    return $rsa->decrypt($data);
  }
}


class ByMessage {
  var $messageId;
  var $timestamp;
  var $data;

  var $aesMode;
  var $aesKey;
  var $aesIv;

  function echoData() {
    $idHex = bin2hex($this->messageId);
    echo "{\"messageId\":\"{$idHex}\"";
    $dataStr = ord($this->data[0]) == 0 ? "hex-".bin2hex($this->data) : $this->data;
    echo ",\"data\":{$dataStr}";
    if ($this->aesMode) {
      echo ",\"aesMode\":{$this->aesMode}";
      $keyHex = bin2hex($this->aesKey);
      echo ",\"aesKey\":{$keyHex}";
      $ivHex = bin2hex($this->aesIv);
      echo ",\"aesIv\":{$ivHex}";
    }
    echo ",\"timestamp\":{$this->timestamp}}<br>";
  }
  public function get_data()
  {
      $dataStr = ord($this->data[0]) == 0 ? "hex-".bin2hex($this->data) : $this->data;
      return $dataStr;
  }
}

function echoHex($data) {
  $hex = bin2hex($data);
  echo "$hex<br>";
}


function dataToBytes($string) {
  return array_merge(unpack("C*", $string));
}

function bytesToData($bytes) {
  return call_user_func_array("pack", array_merge(array("C*"), $bytes));
}


class EncryptException extends Exception {
}
?>
