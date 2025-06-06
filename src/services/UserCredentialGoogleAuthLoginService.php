<?php
namespace rhossis\core\application\authentication\UserCredential\services;

use rhossis\Exception\UserCredentialException;
use rhossis\core\application\authentication\UserCredential\abstractclass\MultiotpWrapper;

/**
 * UserCredentialGoogleAuthLoginService
 * This service implements 2 factor authentication, with the first stage beign Password
 * and second stage being Google Authenticator based TOTP authentication
 * https://github.com/google/google-authenticator
 *
 * @category    
 * @package     rhossis.core.application.authentication.UserCredential.services
 * @copyright   Copyright (c) 2016 Cymap
 * @author      Cyril Ogana <cogana@gmail.com>
 * @abstract
 * 
 * The objectives of the service are
 *  - Implement phase 1 of login using Password Login Service
 *  - Implement phase 2 of login using Google Authentictor TOTP Service
 */
class UserCredentialGoogleAuthLoginService extends UserCredentialPasswordLoginService
{
    protected $userTotpProfile = array();    //the totp profile for the user
    protected $keyLength = null;    //key length to use for generating the one time enc key
    protected $verificationHash = null;    //the hash to verify against    
    protected $oneTimeToken = null;    //the one time token the user inputs for this session
        
    //Constructor method
    public function __construct() {
        
    }
    
    /**
     * initialize the service, bootstrap before any processing
     * 
     * Cyril Ogana <cogana@gmail.com> - 2016-07-06
     * 
     * @access public
     */
    public function initialize() {
        //revert to password only functionality if multi-factor flag is off
        if ($this->_multiFactorFlag === false) {
            parent::initialize();
            return;
        }

        //verify that multi-factor stages is set
        if (
            !(isset($this->_multiFactorStages))
            || !(is_array($this->_multiFactorStages))
            || !(isset($this->_multiFactorStages['current']))
            || !(is_int($this->_multiFactorStages['current']))
            || !(isset($this->_multiFactorStages[1]))
            || !(is_array($this->_multiFactorStages[1]))
            || !(isset($this->keyLength))
        ) {
            throw new UserCredentialException('The multi factor stages register is initialized with an an unknown state', 2100);
        }
        
        //get the current stage (1 or 2)
        $currentStage = $this->_multiFactorStages['current'];
        
        //bootstrap depends on which stage we are in
        if ($currentStage == 1) {
            $this->_multiFactorStages[1]['statuss'] = false;
            parent::initialize();
            return;
        } elseif ($currentStage != 2) {
            throw new UserCredentialException('The current stage of the multi factor auth process is in an unknown state', 2101);
        }
        
        //get the user TOTP profile
        $userTotpProfile = $this->userTotpProfile;
        
        //we are in stage 2, check that all items are in order
        if (
            !(is_array($userTotpProfile))
            || !(isset($userTotpProfile['enc_key']))
            || !(is_string($userTotpProfile['enc_key']))
            || !(isset($userTotpProfile['totp_timestamp']))
            || !($userTotpProfile['totp_timestamp'] instanceof \DateTime)
            || !(isset($userTotpProfile['totp_timelimit']))
            || !(is_int($userTotpProfile['totp_timelimit']))
            || !(isset($this->verificationHash))
            || !(is_string($this->verificationHash))
            || !(isset($this->oneTimeToken))
            || !(is_string($this->oneTimeToken))
        ) {
            throw new UserCredentialException('The user TOTP profile is not initialized properly', 2102);
        }
    }
    
    /**
     * authenticate the user after initialization
     * 
     * Cyril Ogana <cogana@gmail.com> - 2016-07-05
     * 
     * @access public
     */
    public function authenticate() {
        $currentStage = $this->_multiFactorStages['current'];
        
        //set stage as inactive
        if ($currentStage == 1) {
            $this->_multiFactorStages[1]['statuss'] = parent::authenticate();
            
            //stage one sucessfull, bootstrap stage 2
            if ($this->_multiFactorStages[1]['statuss'] === true ) {
                $this->_multiFactorStages[2] = array (
                    'enc_key' => \openssl_random_pseudo_bytes($this->getEncKeyLength()),
                    'statuss' => false
                );                
            }

            return $this->_multiFactorStages;
        } elseif ($currentStage != 2) {
            throw new UserCredentialException('The current stage of the multi factor auth process is in an unknown state', 2101);
        }
        
        //authenticate stage 2
        $totpTimestamp = $this->userTotpProfile['totp_timestamp'];
        $totpTimelimit = $this->userTotpProfile['totp_timelimit'];
        $currDateTime = new \DateTime();
        $totpTimeElapsed = $currDateTime->getTimestamp() - $totpTimestamp->getTimestamp();
        $encKey = $this->userTotpProfile['enc_key'];
        $verificationHash = $this->getVerificationHash();
        $comparisonHash = \crypt($this->getCurrentPassword(), $encKey);

        //initialize verification - comparison
        $verificationEqualsComparison = false;
        
        //verify if verification hash equals comparison hash. Use hash_equals function if exists
        if (!\function_exists('hash_equals')) {
            if ($verificationHash === $comparisonHash) {
                $verificationEqualsComparison = true;
            }
        } else {
            if (\hash_equals($verificationHash, $comparisonHash)) {
                $verificationEqualsComparison = true;
            }
        }

        if (
            !($totpTimeElapsed < $totpTimelimit)
            || !($verificationEqualsComparison === true)
            || !($this->checkToken())
        ) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     *  Set the TOTP profile of the user
     * 
     * Cyril Ogana <cogana@gmail.com> - 2015-07-24
     * 
     * @param  array $totpProfile - TOTP profile array of user
     * 
     * @access public
     */    
    public function setUserTotpProfile(array $totpProfile) {
        if (!(is_array($totpProfile))) {
            throw new UserCredentialException('The TOTP Profile must be an array', 2104);
        }
        
        $this->userTotpProfile = $totpProfile;
    }
   
    /**
     *  Return the user totp profile
     * 
     * Cyril Ogana <cogana@gmail.com> - 2015-07-24
     * 
     * @return array
     * @access public 
     */
    public function getUserTotpProfile(): array {
        return $this->userTotpProfile;
    }
    
    /**
     *  Set the encryption key length
     * 
     * Cyril Ogana <cogana@gmail.com> - 2015-07-22
     * 
     * @param  int $keyLength - Length of the encryption key
     * 
     * @access public
     */    
    public function setEncKeyLength(int $keyLength) {
        $keyLengthCast = (int) $keyLength;
        
        if (!($keyLengthCast > 0)) {
            throw new UserCredentialException('The encryption key length must be an integer', 2105);
        }
        
        $this->keyLength = $keyLengthCast;
    }
   
    /**
     *  Return the encryption key length
     * 
     * Cyril Ogana <cogana@gmail.com> - 2015-07-24
     * 
     * @return int
     * @access public 
     */
    public function getEncKeyLength(): int {
        return $this->keyLength;
    }
    
    
    /**
     *  Set the verification hash for Stage 2 authentication
     * 
     * Cyril Ogana <cogana@gmail.com> - 2015-07-24
     * 
     * @param  string $verificationHash - Hash to verify for stage 2
     * 
     * @access public
     */     
    public function setVerificationHash(string $verificationHash) {
        $this->verificationHash = (string) $verificationHash;
    }
    
    /**
     *  Return the verification hash
     * 
     * Cyril Ogana <cogana@gmail.com> - 2015-07-24
     * 
     * @return string
     * @access public 
     */    
    public function getVerificationHash(): string {
        return $this->verificationHash;
    }

    /**
     *  Set the verification token expected from user
     * 
     * Cyril Ogana <cogana@gmail.com> - 2015-07-24
     * 
     * @param  string $verificationToken - SMS token for the one time login
     * 
     * @access public
     */     
    public function setOneTimeToken(string $verificationToken) {
        $this->oneTimeToken = (string) $verificationToken;
    }
    
    /**
     *  Return the verification token
     * 
     * Cyril Ogana <cogana@gmail.com> - 2015-07-24
     * 
     * @return string - the hashed password
     * @access public
     */    
    public function getOneTimeToken(): string {
        return $this->oneTimeToken;
    } 
    
    /**
     * Verify a user token if it exists as part of the multi factor login process
     * 
     * Cyril Ogana <cogana@gmail.com> - 2016-07-05
     *
     * @return boolean
     * @throws UserCredentialException
     */
    protected function checkToken(): bool {
        //instantiate MultiOtp Wrapper
        $multiOtpWrapper = new MultiotpWrapper();
        
        //get the username
        $currentUserName = $this->getCurrentUsername();
        
        //assert that username is set
        if (!(\strlen((string) $currentUserName))) {
            throw new UserCredentialException('Cannot validate a TOTP token when username is not set!', 2106);
        }
        
        //assert that the token exists
        $tokenExists = $multiOtpWrapper->CheckTokenExists($currentUserName);
        
        if (!($tokenExists)) {
            throw new UserCredentialException('The TOTP token for the current user does not exist', 2107);
        }

        //username mapped to their token name
        $multiOtpWrapper->setToken($currentUserName);
        
        //validate the Token
        $oneTimeToken = $this->getOneTimeToken();
        $tokenCheckResult = $multiOtpWrapper->CheckToken($oneTimeToken);
        
        //The results are reversed
        //TODO: Add intepretation of MultiOtp return results here to enable exception handling
        if ($tokenCheckResult == 0) {
            return true;
        } else {
            return false;
        }
    }
}
