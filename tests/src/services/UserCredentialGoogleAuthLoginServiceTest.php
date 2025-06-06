<?php
namespace rhossis\core\application\authentication\UserCredential\services;

use rhossis\core\application\authentication\UserCredential\abstractclass\MultiotpWrapper;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2016-07-01 at 02:31:35.
 */
class UserCredentialGoogleAuthLoginServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var UserCredentialGoogleAuthLoginService
     */
    protected $object;
    
    /**
     * @var string
     */
    protected $password;
    
    /**
     * @var abstractclass\MultiotpWrapper
     */
    protected $multiOtpWrapper;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() : void
    {
        //Make sure that UserCredentialPasswordLoginService is available to your auth plugin and create an instance        
        $this->object = new UserCredentialGoogleAuthLoginService;
        
        /**
         * This is the password that is stored in DB hashed with \password_hash function. 
         * PHP 5.4 will be supported because of ircmaxell/password-compat package
         */        
        $this->password = \password_hash('123456', \PASSWORD_DEFAULT);
        
        //MultiOtpWrapper for TOTP management
        $this->multiOtpWrapper = new MultiotpWrapper();
        
        /*
         * Create a TOTP token for the user. If it exists, it will not overwrite, but retain the existing one. Token names are mapped to user name
         * Your application will have to handle it, using the one line call below. MultiOTP manages everything else, including encryption of the secret
         */  
        $this->multiOtpWrapper->CreateToken('rhossis');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() : void
    {
    }
   
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::initialize
     */
    public function testInitialize() {
        //username of authenticating user
        $this->object->setCurrentUserName('rhossis');
        
        //password that is stored in the DB
        $this->object->setCurrentPassword($this->password);
        
        //password input by the user in the login form / API
        $this->object->setPassword('123456');
        
        $this->assertEquals(null, $this->object->initialize());     
    }
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::initialize
     */
    public function testInitializeStage1Exception() {
        $this->expectException('\rhossis\Exception\UserCredentialException');
        $this->expectExceptionMessage('The usercredential login service is not initialized with all parameters');
                        
        /**
         *     When you call setMultiFactor() to true, we require more info than just username, hashed password and password logged
         *     
         *     This additional parameters have to be set during Stage 1 are
         *     - The Multi Factor stages aray, which indicates the current stage and has an additional state info. The structure of the
         *       array is :
         * 
         *               array  (
         *                     'current' => $loginStage,
         *                      1 => array  (
         * 
         *                       )
         *              );
         * 
         *         Where 'curent' is the login stage of the multifactor auth transaction and array with key 1 contains stage info. Note
         *         that the state 1 indicates we are in stage 1. Each subsequent stage gets its index (see below)
         * 
         *      - The EncKey Length which is the length of the hash that will change with each login transaction. Default length is 16
         *         and it is generated using \openssl_pseudo_random_bytes. The client must return this hash together with login
         *         info for subsequent login stages e.g during stage 2 otherwise authentication will not occur even with correct token
         * 
         */
        $this->object->initialize();
    }

    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::authenticate
     */
    public function testAuthenticateStage1() {
        $this->object->setMultiFactor(true);
        
        //Set multifactor stages array  as explained above
        $this->object->setMultiFactorStages(array('current' => 1, 1 => array()));
        
        //set EncKey Length as explained above
        $this->object->setEncKeyLength(16);
        
        //Username and password as required by UserCredential service
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword(\password_hash('123456', \PASSWORD_DEFAULT));
        
        //initialize for multi-factor should now work l now work
        $this->object->initialize();
        
        //because we are in Stage 1 the statuss of multi-factor array should have been set false by the class because we are yet to authenticate
        $mFactorStages = $this->object->getMultiFactorStages();
        $this->assertIsBool($mFactorStages[1]['statuss']);
        $this->assertEquals(false, $mFactorStages[1]['statuss']);       
    }
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::initialize
     */
    public function testInitializeStage2Exception() {
        $this->expectException('\rhossis\Exception\UserCredentialException');
        $this->expectExceptionMessage('The user TOTP profile is not initialized properly');
                        
        /**
         *     When you call setMultiFactor() to true, we require more info than just username, hashed password and password logged
         *     
         *     This additional parameters have to be set during Stage 2 are
         * 
         *     - The TOTP Profile array, which contains some information about the generated token. The structure of the TOTP array is:
         *          
         *              array  (
         *                  'enc_key' => $encKey,                              //enc key generated using
         *                  'totp_timestamp" => $totpTimestamp,    //timestamp when the login transaction opened
         *                  'totp_timelimit'   => $totpTimelimit       //time limit for user to complete stage 2 (180 seconds by default)
         *             );
         *   
         *     - The Verification Hash. This is a hash computed with \crypt, and salted with the unique EncKey generated in stage 1
         * 
         *     - One Time Token: This is the one time token input by the user in the Login Screen / API        
         */
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 2, 1 => array()));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        $this->object->setPassword('1234567');
        $this->object->initialize();
    }
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::initialize
     */
    public function testInitializeStage2() {
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 1, 1 => array()));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        $this->object->setPassword('123456');
        $this->object->initialize();
        $authResult = $this->object->authenticate(); 
        $encKey = $authResult[2]['enc_key'];        
        $verificationHash = \crypt($this->object->getPassword(), $authResult[2]['enc_key']);
        $nowObj = new \DateTime();
        $nowObj->setTimestamp(($nowObj->getTimestamp() - 140));
        $totpTimeLimit = 180;

        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 2, 1 => array('statuss' => true)));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        
        $totpProfile = array (
           'enc_key' => $encKey,
           'totp_timestamp' => $nowObj,
           'totp_timelimit' => $totpTimeLimit
        );
        $this->object->setUserTotpProfile($totpProfile);
        $this->object->setVerificationHash($verificationHash);
        $oneTimeToken = \password_hash('123456', \PASSWORD_DEFAULT);
        $this->object->setOneTimeToken($oneTimeToken); 
        
        //This should go through
        $this->object->initialize();
        $this->assertEquals(1, true);
    }    
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::authenticate
     */
    public function testAuthenticateStage2() {
        //Stage 1 Authentication        
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 1, 1 => array()));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        $this->object->setPassword('123456');
        $this->object->initialize();
        $authResult = $this->object->authenticate(); 
        $encKey = $authResult[2]['enc_key'];        
        $verificationHash = \crypt($this->object->getCurrentPassword(), $authResult[2]['enc_key']);
        $nowObj = new \DateTime();
        $nowObj->setTimestamp(($nowObj->getTimestamp() - 140));
        $totpTimeLimit = 180;

        //Stage 2 Authentication        
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 2, 1 => array('statuss' => true)));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        
        $totpProfile = array (
           'enc_key' => $encKey,
            'totp_timestamp' => $nowObj,
            'totp_timelimit' => $totpTimeLimit
        );
        
        /**
         * For This stage of authentication, three things must be Validated
         * 
         *     1) Token Input by user in Login Screen / API must match the Expected Token Generated
         * 
         *     2) Token must have not been input later than totp_timelimit seconds. You should not need this
         *          when using Google Authenticator because of 30 second limit, but this default 30 second limit
         *          is parameterized and thus this condition is still activated
         * 
         *     3)  The VerificationHash recalculated by \crypt using the EncKey must match the one generated
         *          in Stage 1
         */
        $this->object->setUserTotpProfile($totpProfile);
        $this->object->setVerificationHash($verificationHash);
        
        //Fetch the  token using the username
        $this->multiOtpWrapper->SetToken('rhossis');
       //die(print_r($this->multiOtpWrapper));
        $tokenSeed = $this->multiOtpWrapper->GetTokenSeed('rhossis');
        
        //We use Google2FA class which does its own TOTP calculation using the seed provided. This would be your users 2FA app e.g. on mobile phone.
        $TimeStamp    = \Google2FA::get_timestamp();
        $secretKey = hex2bin($tokenSeed);
        
        //Token  generation to be keyed in by user from their 2FA App
        $oneTimeToken = \Google2FA::oath_hotp($secretKey, $TimeStamp);
        //die($oneTimeToken);
        
        //Set the Token that the user keyed in on screen / API
        $this->object->setOneTimeToken($oneTimeToken);        
        $this->object->initialize();
        
        //Authenticate the Token
        $authResultStage2 = $this->object->authenticate();
        $this->assertEquals(true, $authResultStage2);
    }
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::authenticate
     */    
    public function testAuthenticateStage2UsernameNotSetException() {
        $this->expectException('\rhossis\Exception\UserCredentialException');
        $this->expectExceptionMessage('Cannot validate a TOTP token when username is not set');
                        
        //An Exception Should Be Thrown if CurrrentUsername is not set because username is used to fetch the token from MultiOTP
        $this->object->setCurrentUsername('');
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 1, 1 => array()));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('');
        $this->object->setCurrentPassword($this->password);
        $this->object->setPassword('123456');
        $this->object->initialize();
        $authResult = $this->object->authenticate(); 
        $encKey = $authResult[2]['enc_key'];        
        $verificationHash = \crypt($this->object->getCurrentPassword(), $authResult[2]['enc_key']);
        $nowObj = new \DateTime();
        $nowObj->setTimestamp(($nowObj->getTimestamp() - 140));
        $totpTimeLimit = 180;

        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 2, 1 => array('statuss' => true)));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('');
        $this->object->setCurrentPassword($this->password);
        
        $totpProfile = array (
           'enc_key' => $encKey,
            'totp_timestamp' => $nowObj,
            'totp_timelimit' => $totpTimeLimit
        );
        $this->object->setUserTotpProfile($totpProfile);
        $this->object->setVerificationHash($verificationHash);
        $oneTimeToken = \password_hash('123456', \PASSWORD_DEFAULT);
        $this->object->setOneTimeToken($oneTimeToken);        
        $this->object->initialize();
        $authResultStage2 = $this->object->authenticate();
        $this->assertEquals(true, $authResultStage2);        
    }
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::authenticate
     */    
    public function testAuthenticateStage2TokenNotCreatedException() {
        $this->expectException('\rhossis\Exception\UserCredentialException');
        $this->expectExceptionMessage('The TOTP token for the current user does not exist');
                        
        //An Exception Should Be Thrown if the token for a given username has not been created. Your application should handle this by calling $multiOtpWrapper->CreateToken($userName)
        $this->object->setCurrentUsername('alba');
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 1, 1 => array()));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('');
        $this->object->setCurrentPassword($this->password);
        $this->object->setPassword('123456');
        $this->object->initialize();
        $authResult = $this->object->authenticate(); 
        $encKey = $authResult[2]['enc_key'];        
        $verificationHash = \crypt($this->object->getCurrentPassword(), $authResult[2]['enc_key']);
        $nowObj = new \DateTime();
        $nowObj->setTimestamp(($nowObj->getTimestamp() - 140));
        $totpTimeLimit = 180;

        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 2, 1 => array('statuss' => true)));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('alba');
        $this->object->setCurrentPassword($this->password);
        
        $totpProfile = array (
           'enc_key' => $encKey,
            'totp_timestamp' => $nowObj,
            'totp_timelimit' => $totpTimeLimit
        );
        $this->object->setUserTotpProfile($totpProfile);
        $this->object->setVerificationHash($verificationHash);
        $oneTimeToken = \password_hash('123456', \PASSWORD_DEFAULT);
        $this->object->setOneTimeToken($oneTimeToken);        
        $this->object->initialize();
        $authResultStage2 = $this->object->authenticate();
        $this->assertEquals(true, $authResultStage2);        
    }
    
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::authenticate
     */
    public function testAuthenticateStageTimelimit() {
        //This should not be an eventuality because Google 2FA is 30 seconds, our  window is 180 seconds
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 1, 1 => array()));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        $this->object->setPassword('123456');
        $this->object->initialize();
        $authResult = $this->object->authenticate(); 
        $encKey = $authResult[2]['enc_key'];        
        $verificationHash = \crypt($this->object->getCurrentPassword(), $authResult[2]['enc_key']);

        //Simulate a delay by one second
        $nowObj = new \DateTime();
        $nowObj->setTimestamp(($nowObj->getTimestamp() - 181));
        $totpTimeLimit = 180;

        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 2, 1 => array('statuss' => true)));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        
        $totpProfile = array (
            'enc_key' => $encKey,
            'totp_timestamp' => $nowObj,
            'totp_timelimit' => $totpTimeLimit
        );
        $this->object->setUserTotpProfile($totpProfile);
        $this->object->setVerificationHash($verificationHash);

        $this->multiOtpWrapper->SetToken('rhossis');
       //die(print_r($this->multiOtpWrapper));
        $tokenSeed = $this->multiOtpWrapper->GetTokenSeed('yebo32');
        $TimeStamp    = \Google2FA::get_timestamp();
        $secretKey = hex2bin($tokenSeed);
        $oneTimeToken = \Google2FA::oath_hotp($secretKey, $TimeStamp);
        //die($oneTimeToken);
        
        $this->object->setOneTimeToken($oneTimeToken);        
        $this->object->initialize();
        $authResultStage2 = $this->object->authenticate();
        $this->assertEquals(false, $authResultStage2);        
    }
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::authenticate
     */
    public function testAuthenticateStageTokenWrong() {
        //This should fail. User input a wrong token
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 1, 1 => array()));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        $this->object->setPassword('123456');
        $this->object->initialize();
        $authResult = $this->object->authenticate(); 
        $encKey = $authResult[2]['enc_key'];        
        $verificationHash = \crypt($this->object->getCurrentPassword(), $authResult[2]['enc_key']);

        $nowObj = new \DateTime();
        $nowObj->setTimestamp(($nowObj->getTimestamp() - 181));
        $totpTimeLimit = 180;

        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 2, 1 => array('statuss' => true)));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        
        $totpProfile = array (
           'enc_key' => $encKey,
            'totp_timestamp' => $nowObj,
            'totp_timelimit' => $totpTimeLimit
        );
        $this->object->setUserTotpProfile($totpProfile);
        $this->object->setVerificationHash($verificationHash);

        $this->multiOtpWrapper->SetToken('rhossis');
       //die(print_r($this->multiOtpWrapper));
        $tokenSeed = $this->multiOtpWrapper->GetTokenSeed('yebo32');
        $TimeStamp    = \Google2FA::get_timestamp();
        $secretKey = hex2bin($tokenSeed . 'a2');
        $oneTimeToken = \Google2FA::oath_hotp($secretKey, $TimeStamp);
        //die($oneTimeToken);
        
        $this->object->setOneTimeToken($oneTimeToken);        
        $this->object->initialize();
        $authResultStage2 = $this->object->authenticate();
        $this->assertEquals(false, $authResultStage2);   
    }    
    
    /**
     * @covers rhossis\core\application\authentication\UserCredential\services\UserCredentialGoogleAuthLoginService::authenticate
     */
    public function testAuthenticateStageEncKeyWrong() {
        //This should fail. Requesting Application did not respond with the correct Verification Hash generated in Stage 1
        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 1, 1 => array()));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        $this->object->setPassword('123456');
        $this->object->initialize();
        $authResult = $this->object->authenticate(); 
        $encKey = $authResult[2]['enc_key'];        
        $verificationHash = \crypt($this->object->getCurrentPassword(), $authResult[2]['enc_key']);

        $nowObj = new \DateTime();
        $nowObj->setTimestamp(($nowObj->getTimestamp() - 181));
        $totpTimeLimit = 180;

        $this->object->setMultiFactor(true);
        $this->object->setMultiFactorStages(array('current' => 2, 1 => array('statuss' => true)));
        $this->object->setEncKeyLength(16);
        $this->object->setCurrentUserName('rhossis');
        $this->object->setCurrentPassword($this->password);
        
        $totpProfile = array (
           'enc_key' => 'hElLoThErEiAmAwRoNgEnCkEy',
            'totp_timestamp' => $nowObj,
            'totp_timelimit' => $totpTimeLimit
        );
        $this->object->setUserTotpProfile($totpProfile);
        $this->object->setVerificationHash($verificationHash);

        $this->multiOtpWrapper->SetToken('rhossis');
       //die(print_r($this->multiOtpWrapper));
        $tokenSeed = $this->multiOtpWrapper->GetTokenSeed('yebo32');
        $TimeStamp    = \Google2FA::get_timestamp();
        $secretKey = hex2bin($tokenSeed);
        $oneTimeToken = \Google2FA::oath_hotp($secretKey, $TimeStamp);
        //die($oneTimeToken);
        
        $this->object->setOneTimeToken($oneTimeToken);        
        $this->object->initialize();
        $authResultStage2 = $this->object->authenticate();
        $this->assertEquals(false, $authResultStage2);           
    } 
    /*public function testMultiotp() {
        $a = new \rhossis\core\application\authentication\UserCredential\abstractclass\MultiotpWrapper();
        $b = $a->GenerateGoogleAuthenticatorTotpToken();
        die(print_r($b . ' is the result'));
    }*/
}
