<?php

namespace Drupal\casestudy\Utility;

/**
 * Provides helper to operate on arrays.
 */
class GeneralHelper {


  /**
   * encrypt the cookie data.
   *
   * @param array $data
   *   An associative array.
   *
   * @return encrypted has value
   *   Empty if data array is empty.
   *
   */
  public static function encryptData(array $data) {
      $salt = 'easy_to_guess_but_hard_to_rememb';
      if (!is_array($data))
          return;
      $text = serialize($data);
      return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $salt, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));

  }

    /**
     * encrypt the cookie data.
     *
     * @param string $cookie_name
     *
     * @return decrypted value
     *   Empty if data array is empty.
     *
     */
    public static function decryptCookieData($cookie_name) {
        if (!isset($_COOKIE[$cookie_name])) {
            $data = array();
            return $data;
        }

        $cookie_value = $_COOKIE[$cookie_name];
        //$salt = 'easy_to_guess_but_hard_to_remember'; // 34 bit key is not supported in php 5.6
        $salt = 'easy_to_guess_but_hard_to_rememb';
        $data = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $salt, base64_decode($cookie_value), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
        $undata = unserialize($data);
        return $undata;
    }

}
