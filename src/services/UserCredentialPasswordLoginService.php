<?php
namespace rhossis\core\application\authentication\UserCredential\services;

use rhossis\Exception\UserCredentialException;
use rhossis\core\application\authentication\UserCredential\abstractclass\UserCredentialAuthenticationInterface;
use rhossis\core\application\authentication\UserCredential\traits\UserCredentialAuthenticationTrait;
    
/**
 * UserCredentialPasswordLoginService
 * This service creates password hashes using the BCRYPT cipher
 *
 * @category    
 * @package     rhossis.core.application.authentication.UserCredential.services
 * @copyright   Copyright (c) 2014 Cymap
 * @author      Cyril Ogana <cogana@gmail.com>
 * @abstract
 * 
 * The objectives of the service are
 *  - Create password hash
 *  - Verify password for log in authentication
 *  @TODO provide ability to use AES to encrypt the hash
 */

class UserCredentialPasswordLoginService implements UserCredentialAuthenticationInterface
{
    use UserCredentialAuthenticationTrait {
        UserCredentialAuthenticationTrait::authenticate as authenticateByPlatform;
    }
    
    //flags
    protected $_usePasswordFlag = true;  //whether the auth is password based (at some stage or fully)
    protected $_multiFactorFlag = false; //whether the auth service is multi factor
    
    //user info
    protected $_inputPassword   = ''; //the input password
    protected $_currentUsername = ''; //username
    protected $_currentPassword = ''; //hashed password
    
    //multi factor auth
    protected $_multiFactorHandler = null;    //the handler instance for mutli factor auth (if delegated)
    protected $_multiFactorStages  = array(); //the stages of multi factor auth (if to be delegated)
    
    //password login platform (default is native)
    protected $_passwordAuthenticationPlatform = 1;
    
    //platform settings for the appropriate platform
    protected $_passwordAuthenticationPlatformSettings = array();
    
    //Constructor method
    public function __construct() {
        
    }        
  
    /**
     * function setUserPassword() - Specify whether the method uses password
     *                              (set e.g. user log in, lDAP, 2 FACTOR (step 1)
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     *
     * @param bool $flag - if true, is using password
     * 
     * @access public
     */             
    public function setUsePassword(bool $flag) {
        $this->_usePasswordFlag = (bool) $flag;
    }

    /**
     * function getUsePassword() - Return the use password flag
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     *
     * @return bool
     * 
     * @access public
     */             
    public function getUsePassword(): bool {
        return $this->_usePasswordFlag;
    }
    
    /**
     * function setPassword() - Set the user password, and hash it
     *
     * Cyril Ogana <cogana@gmail.com>- 2014-02-13
     *
     * @param string $password - the user password in raw text
     *
     * @access public
     */             
    public function setPassword(string $password) {
        $this->_inputPassword = (string) $password;
    }
    
    /**
     * function getPassword()  - Return the hashed user password
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     * 
     * @param  bool $unhashed - flag if true, return unhashed
     * 
     * @return string - the hashed password
     * 
     * @access public
     */
    public function getPassword(bool $unhashed = false): string {
        if((bool) $unhashed === true){
            return $this->_inputPassword;
        }else{
            return \password_hash($this->_inputPassword, \PASSWORD_DEFAULT);
        }
    }
    
    /**
     * function setMultiFactor($flag) - Set whether this service uses multi factor auth
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     * 
     * @param bool $flag - if true, is a multi factor auth service
     * 
     * @access public
     */
    public function setMultiFactor(bool $flag) {
        $this->_multiFactorFlag = (bool) $flag;
    }
    
    /**
     * function setMultiFactorHandler - Provide namespace of the multi factor handler service,
     *                                  which has to implement the interface
     *                                  rhossis\core\application\authentication\abstractclass\UserCredentialAuthenticationMultiFactorInterface
     *
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     * 
     * @param string $handler - The namespace of the multi factor handler service
     * 
     * @access public 
     */
    public function setMultiFactorHandler(string $handler) {
        $this->_multiFactorHandler = (string) $handler;
    }
    
    /**
     * function getMultiFactorHandler - Return an instance of the multi factor handler service
     *                                  to use ofr this authentication session
     * 
     * Cyril Ogana <cogana@gmail.com > - 2014-02-13
     * 
     * @return object
     * 
     * @access public
     */
    public function getMultiFactorHandler(): object {
        return $this->_multiFactorHandler;
    }
    
    /**
     * function setMultiFactorStages - in an array, configure the steps of the multifactor login, passing
     *                                 numeric stage names, types and handler calls
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     * 
     * @param array $stages - The stages of the log in session
     * 
     * @access public
     */
    public function setMultiFactorStages(array $stages) {
        $this->_multiFactorStages = $stages;
    }
    
    /**
     * function getMultiFactorStages - return the multi factor stages array
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     * 
     * @return array
     * 
     * @access public
     */
    public function getMultiFactorStages(): array {
        return $this->_multiFactorStages;
    }
    
    /**
     * function initialize() - initialize the service, bootstrap before any processing
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     * 
     * @access public
     */
    public function initialize() {
        if(($this->_inputPassword == '')
            && ($this->_currentUsername == '')
            && ($this->_currentPassword == '')
        ){
            throw new UserCredentialException("The usercredential login service is not initialized with all parameters", 2000);
        }
    }
    
    /**
     * function authenticate() - authenticate the user after initialization
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     * 
     * @access public
     */
    public function authenticate() {
        return $this->authenticateByPlatform();
    }
    
    /**
     * function setCurrentUsername($username) - set the current username
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-13
     * 
     * @param string $username - The current username
     * 
     * @access public
     */
    public function setCurrentUsername(string $username) {
        $this->_currentUsername = (string) $username;
    }
    
    /**
     * function getCurrentUsername() - get the current username
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-14
     * 
     * @return string - Return the current username
     * 
     * @access public
     */
    public function getCurrentUsername(): string {
        return $this->_currentUsername;
    }
    
    /**
     * function setCurrentPassword() - set the current password
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-14
     * 
     * @param string  $password - The current password hash
     * 
     * @access public
     */
    public function setCurrentPassword(string $password) {
        $this->_currentPassword = $password;
    }
    
    /**
     * function getCurrentPassword() - return the current password (hashed)
     * 
     * Cyril Ogana <cogana@gmail.com> - 2014-02-14
     * 
     * @return string - The hashed password
     * 
     * @access public48
     */
    public function getCurrentPassword(): string {
        return $this->_currentPassword;
    }
    
    /**
     * Set the authentication platform  to use
     * 
     *  Cyril Ogana <cogana@gmail.com> 
     *  2018
     * 
     * @param int $authenticationPlatform
     */
    public function setPasswordAuthenticationPlatform(int $authenticationPlatform) {
        $this->_passwordAuthenticationPlatform = $authenticationPlatform;
    }
    
    /**
     * Get the authentication platform  to use
     * 
     *  Cyril Ogana <cogana@gmail.com> 
     *  2018
     * 
     * @return  int
     */
    public function getPasswordAuthenticationPlatform(): int {
        return $this->_passwordAuthenticationPlatform;
    }

    /**
     * Set the the password authentication platform settings for the appropriate platform
     * 
     *  Cyril Ogana <cogana@gmail.com> 
     *  2018
     * 
     * @param  array
     */    
    public function setPasswordAuthenticationPlatformSettings(array $platformSettings) {
        $this->_passwordAuthenticationPlatformSettings = $platformSettings;
    }
    
    /**
     * Get the the password authentication platform settings for the appropriate platform
     * 
     *  Cyril Ogana <cogana@gmail.com> 
     *  2018
     * 
     * @return array
     */
    public function getPasswordAuthenticationPlatformSettings(): array {
        return $this->_passwordAuthenticationPlatformSettings;
    }
}
