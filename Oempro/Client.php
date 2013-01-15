<?php

namespace Oempro;

/**
 * Class to communicate with OemPro via the API
 *
 * @copyright   2008-2009 Ricca Group, Inc.
 * @author      Mark Mitchell <mmitchell@riccagroup.com>
 * @package     MedSurvey Direct
 * @version     2.0
 **/
class Client
{

    /**
     * Session Id
     *
     * @var string
     */
    private $sessionID = null;

	/**
	 * API URL of OEMPRO
	 *
	 * @var	string
	 */
	private $url = 'http://some.host.com/oempro/api.php?';

	/**
	 * The conrtoller that is using this component
	 *
	 * @var	object
	 */
	private $controller = null;

	/**
	 * Snoopy browser object
	 * Initalized in startup()
	 *
	 * @var	object
	 */
	protected $snoopy = null;

	/**
	 * Last result from a snoopy call that resulted in anything but a HTTP 200 success
	 *
	 * @var	string
	 */
	protected $lastErrorResult = null;

	/**
	 * Set this to true to ouput HTTP request debugging
	 *
	 * @var	boolean
	 */
	public $debug = false;

	/**
	 * Sticky session key name that will be sent as a cookie for ensuring API requests
	 * stay on the same server. This is needed when importing data
	 */
	public $StickySession = 'SessionID';

    function __construct() {
		$this->snoopy = new Snoopy();
		$this->snoopy->accept = 'application/json';
        $this->snoopy->port_in_host = false;
		$this->snoopy->set_submit_normal();
    }

	public function setApiURL($url){
		$this->url = $url;
	}

    /**
     * Set Omepro User Session Id
     *
     * @param  string   $sid   session id after user successful login
     * @return null
     */
    public function setSessionID($sid){
        $this->sessionID = $sid;
    }

    /**
     * Get Omepro User Session Id
     *
     * @return string       session id
     */
    public function getSessionID(){
        return $this->sessionID;
    }


    /**
     * Helper function to check user param matches with required parameter and its not null
     *
     * @param     array         $rparam    required parameter array
     * @param	  array         $optparam	Optional parameter array
     * @param     array         $uparam    user parameter array
     * @required  \stdClass      $error_stdclass
     *
     *                              On Success / Failure
     *                              ----------
     *                               \stdClass Object
     *                              (
     *                                 [code] => 1
     *                                 [description] => \stdClass Object
     *                               }
     *
     */
    private function _check_req_opt_param($rparam = array() , $optparam = array(), $uparam = array() ){
        // Make sure all passed paramaters are arrays
		if ( ! is_array($rparam)
			|| ! is_array($optparam)
			|| ! is_array($uparam)) {
			$error_stdclass = new \stdClass;
			$error_stdclass->Success = 0;
			$error_code_desc_stdclass = new \stdClass;
			$error_code_desc_stdclass->code = 96;
			$error_code_desc_stdclass->description = "Parameters must be arrays";
			$error_stdclass->ErrorCode = $error_code_desc_stdclass;
			return $error_stdclass;
		}
        //Final User i/p array
        $uparam_final_array = array();

        //Checking if req parameter exsist in user parameter
        for($i = 0; $i < sizeof($rparam) ; $i++){

            $req_param_chk = array_key_exists($rparam[$i], $uparam);

            //If Key Dont Exsist
            if($req_param_chk == false){

                //Error Message
                $error_stdclass = new \stdClass;
                $error_stdclass->Success = 0;
                $error_code_desc_stdclass = new \stdClass;
                $error_code_desc_stdclass->code = 98;
                $error_code_desc_stdclass->description = "Required Parameter: '". $rparam[$i]."' is missing";
                $error_stdclass->ErrorCode = $error_code_desc_stdclass;
                return $error_stdclass;

            } else { //If Key Present

                // Checking If not set
                if(!isset($uparam[$rparam[$i]])){

                    //Error Message
                    $error_stdclass = new \stdClass;
                    $error_stdclass->Success = 0;
                    $error_code_desc_stdclass = new \stdClass;
                    $error_code_desc_stdclass->code = 99;
                    $error_code_desc_stdclass->description = "Parameter: '".$rparam[$i]."' - must be specified";
                    $error_stdclass->ErrorCode = $error_code_desc_stdclass;
                    return $error_stdclass;

                } else { //if Not empty

                    $uparam_final_array[$rparam[$i]] = $uparam[$rparam[$i]];
                    unset($uparam[$rparam[$i]]);
                }
            }
        }

        // Optional Parameter Check
        for($j = 0; $j < sizeof($optparam) ; $j++){

            $optl_param_chk = array_key_exists($optparam[$j], $uparam);

            //If Key Dont Exsist
            if($optl_param_chk == false){
                $uparam_final_array[$optparam[$j]] = null;

            } else { //if Key Present

                //Checking if empty
                if(empty($uparam[$optparam[$j]])){
                    $uparam_final_array[$optparam[$j]] = null;
                } else { //if Not empty
                    $uparam_final_array[$optparam[$j]] = $uparam[$optparam[$j]];
                }
                unset($uparam[$optparam[$j]]);
            }
        }

        // Checking for invalid user parameters
        if(sizeof($uparam) >= 1){
            //Error Message
            $error_stdclass = new \stdClass;
            $error_stdclass->Success = 0;
            $error_code_desc_stdclass = new \stdClass;
            $error_code_desc_stdclass->code = 97;
            $error_code_desc_stdclass->description = 'Invalid parameters for this command ('.join(',',array_keys($uparam)).')';
            $error_stdclass->ErrorCode = $error_code_desc_stdclass;
            return $error_stdclass;
        }


        //Success
        $error_stdclass = new \stdClass;
        $error_stdclass->Success = 1;
        $error_code_desc_stdclass = new \stdClass;
        $error_code_desc_stdclass->code = 0;
        $error_code_desc_stdclass->description = 'No Error';
        $error_stdclass->ErrorCode = $error_code_desc_stdclass;
        $error_stdclass->new_options = $uparam_final_array;
        return $error_stdclass;
    }



    /**
     * Helper Function - Format Error Decription
     *
     * @param \stdClass object   $result            raw version
     * @param array             $error_code_desc
     * @return \stdClass object  $result             formated version
     */
    private function _error_description_format($result, $error_code_desc){

        //Success
        if(!empty($result->Success) &&
            $result->Success == 1){

            //Error Description - Formatting
            $error_stdclass = new \stdClass;
            $error_desc = $error_code_desc[$result->ErrorCode];
            $error_stdclass->code = $result->ErrorCode;
            $error_stdclass->description = $error_desc;
            $result->ErrorCode = $error_stdclass;

        } else {

            //Setting Success flag to 0
            $result->Success = 0;

            //Error Description - Formatting
            $error_stdclass = new \stdClass;

            if(!empty($result->ErrorCode) && is_array($result->ErrorCode)){

                //Error Code
                $error_stdclass->code = $result->ErrorCode[0];

                //Checking if error code exsist
                if(array_key_exists($result->ErrorCode[0] ,$error_code_desc)){
                    $error_desc = $error_code_desc[$result->ErrorCode[0]];
                } else {
                    $error_desc = 'Error description not found';
                }
                $error_stdclass->description = $error_desc;

            } else {
                if ( empty($result->ErrorCode) ) {
                     $error_desc = 'Error description not found';
                } else {
                    //Error Code
                    $error_stdclass->code = $result->ErrorCode;
                    //Checking if error code exsist
                    if(array_key_exists($result->ErrorCode ,$error_code_desc)){
                         $error_desc = $error_code_desc[$result->ErrorCode];
                    } else {
                         $error_desc = 'Error description not found';
                    }
                }
                $error_stdclass->description = $error_desc;
            }

            $result->ErrorCode = $error_stdclass;
        }

        return $result;
    }

    /************************************************************
     *                Administrators Operation                  *
     *************************************************************/


    /**
     * Admin Login into OemPro , and sets SessionID
     *
     * @param   array            $options = array(
     *                                            'Username' => Username of the client to be logged in {string} (required)
     *                                            'Password' => Password of the client to be logged in {string} (required)
     *                                           )
     *
     * @return  \stdClass          $result
     *
     *                              On Success / Failure
     *                              ----------
     *                               \stdClass Object
     *                              (
     *                                 [Success] => 1
     *                                 [ErrorCode] => \stdClass Object
     *                                     (
     *                                         [code] => Error Code
     *                                         [description] => Error description
     *                                     )
     *
     *                                 //On Success
     *                                 [SessionID] => Session ID Created
     *                                 [AdminInfo] => \stdClass Object
     *                                     (
     *                                         [AdminID] => Admin id
     *                                         [Name] => Admin Name
     *                                         [EmailAddress] => Admin Email Addess
     *                                         [Username] => Admin User name
     *                                         [Password] => Admin Password - Encrypted
     *                                     )
     *                             )
     */
    public function adminLogin($options = array()) {

        //Required Parameters
        $req_param = array('Username', 'Password');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param , $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('Command'        => 'Admin.Login',
                                         'ResponseFormat' => 'JSON',
                                         'DisableCaptcha' => true,
                                         'Captcha'        => null,
                                         'RememberMe'     => null), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Username is missing',
                                 '2' => 'Password is missing',
                                 '3' => 'Invalid login information',
                                 '4' => 'Invalid image verification',
                                 '5' => 'Image verification failed',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result = $this->_callAPI($this->url, $post_params, $this->debug, false);

        //if Succes - Setting SessionID
        if($result->Success == 1){
            $this->setSessionID($result->SessionID);
        }

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Sends the password reset email.
     *
     * @param   array            $options = array(
     *                                            'EmailAddress' => Email address of the admin to be reminded  {string} (required)
     *                                           )
     *
     * @return \stdClass         $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                           )
     *
     */
    public function adminPasswordRemind($options = array()){

        //Required Parameters
        $req_param = array('EmailAddress');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('Command'        => 'Admin.PasswordRemind',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Email address is missing',
                                 '2' => 'Invalid email address',
                                 '3' => 'Email address not found in admin database',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Resets admin password and sends the new password with email.
     *
     * @param   array            $options = array(
     *                                            'AdminID' => ID of the admin whose password will be reset  {integer} (required)
     *                                            'CustomResetLink' => If you want to display a custom password reset link inside the sent email, enter it here with base64_encoded and then rawurlencoded  {string} (optional)
     *                                           )
     *
     * @param integer           $AdminID    ID of the admin whose password will be reset
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                           )
     */
    public function adminPasswordReset($options = array()){

        //Required Parameters
        $req_param = array('AdminID');

        //Optinal Parameters
        $optl_param = array('CustomResetLink');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('Command'        => 'Admin.PasswordReset',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Admin ID is missing',
                                 '2' => 'Invalid admin id',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Update admin account information
     *
     * @param   array            $options = array(
     *                                            'AdminID' => ID number of the admin account {integer} (required)
     *                                            'Username' => Username of the admin account {string} (required)
     *                                            'Password' => Password of the admin account {string} (required)
     *                                            'EmailAddress' => Email address of the admin account {string} (required)
     *                                            'Name' => Name of the admin account  {string} (required)
     *                                           )
     *
     * @return \stdClass         $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                           )
     */
    public function adminUpdate($options = array()){

        //Required Parameters
        $req_param = array('AdminID', 'Username', 'Password', 'EmailAddress', 'Name');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'      => $this->getSessionID(),
                                         'Command'        => 'Admin.Update',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Admin name is missing',
                                 '2' => 'Admin username is missing',
                                 '3' => 'Admin password is missing',
                                 '4' => 'Admin email address is missing',
                                 '5' => '(reserved)',
                                 '6' => 'Admin ID is missing',
                                 '7' => 'Email address format is invalid',
                                 '8' => 'You are not authorized to update another admin account',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                Attachment Operation                       *
     *************************************************************/

    /**
     * Delete attachments
     *
     * @param   array            $options = array(
     *                                            'AttachmentID' => ID of the attachment that will be deleted  {integer} (required)
     *                                           )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                           )
     */
    public function attachmentDelete($options = array()){

        //Required Parameters
        $req_param = array('$AttachmentID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'      => $this->getSessionID(),
                                         'Command'        => 'Attachment.Delete',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Attachment id is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /************************************************************
     *                AutoResponder Operation                   *
     *************************************************************/


    /**
     * Creates a new auto responder for given subscriber list.
     *
     * @param   array  $options = array(
     *                                 'SubscriberListID' => ID of the subscriber list that auto responder will be created for. {integer} (required)
     *                                 'EmailID' => Email ID  {integer} (required)
     *                                 'AutoResponderName' => Name of auto responder.  {string} (required)
     *                                 'AutoResponderTriggerType' => Type of auto responder trigger.{OnSubscription | OnSubscriberLinkClick | OnSubscriberForwardToFriend} (required)
     *                                 'AutoResponderTriggerValue' => Value of auto responder trigger. {string} (required)
     *                                 'TriggerTimeType' => Trigger time type. {Immediately | Seconds later | Minutes later | Hours later | Days later | Weeks later | Months later} (required)
     *                                 'TriggerTime' => Trigger time.  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                                //0n Success
     *                               [AutoResponderID] => ID of the new auto responder
     *                           )
     */
    public function autoResponderCreate($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'EmailID' ,
                           'AutoResponderName',
                           'AutoResponderTriggerType');

        //Optinal Parameters
        $optl_param = array('AutoResponderTriggerValue',
                            'TriggerTimeType',
                            'TriggerTime');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'      => $this->getSessionID(),
                                         'Command'        => 'AutoResponder.Create',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '2' => 'Auto responder name is missing',
                                 '3' => 'Auto responder trigger type is missing',
                                 '4' => 'Auto responder trigger value is missing',
                                 '5' => 'Auto responder trigger time is missing',
                                 '6' => 'Auto responder trigger type is invalid',
                                 '7' => 'Auto responder trigger time type is invalid',
                                 '8' => 'Missing email ID',
                                 '9' => 'Invalid email ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    //TO - DO
    public function autoResponderUpdate(){}

    /**
     * Retrieves auto responders of given subscriber list.
     *
     * @param   array  $options = array(
     *                                 'OrderField' => Order field {field name of auto responder} (required)
     *                                 'OrderType' => Order type {ASC | DESC} (required)
     *                                 'SubscriberListID' => Subscriber list ID {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TotalAutoResponders] => Total number of auto responders of subscriber list
     *                               [AutoResponders ] => Returned auto responders
     *                           )
     *
     */
    public function autoRespondersGet($options = array()){

        //Required Parameters
        $req_param = array('OrderField',
                           'OrderType',
                           'SubscriberListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'      => $this->getSessionID(),
                                         'Command'        => 'AutoResponders.Get',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Deletes given auto responders
     *
     * @param   array  $options = array(
     *                                 'AutoResponders ' => Comma delimeted auto responder ids. {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function autoRespondersDelete($options = array()){

        //Required Parameters
        $req_param = array('AutoResponders');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'      => $this->getSessionID(),
                                         'Command'        => 'AutoResponders.Delete',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Auto responder ID numbers are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Copies auto responders of a subscriber list to another subscriber list.
     *
     * @param   array  $options = array(
     *                                 'SourceListID' => ID of source subscriber list  {integer} (required)
     *                                 'TargetListID' => ID of target subscriber list  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function autoRespondersCopy($options = array()){

        //Required Parameters
        $req_param = array('SourceListID',
                           'TargetListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'      => $this->getSessionID(),
                                         'Command'        => 'AutoResponders.Copy',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Source subscriber list id is missing',
                                 '2' => 'Target subscriber list id is missing',
                                 '3' => 'Source subscriber list id is invalid',
                                 '4' => 'Target subscriber list id is invalid',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /************************************************************
     *                  Campaigns Operation                      *
     *************************************************************/


    /**
     * Create new campaign to send out
     *
     * @param   array  $options = array(
     *                                 'CampaignName' => Name of the campaign {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                                //On Success
     *                               [CampaignID] => ID of the new campaign record
     *                           )
     */
    public function campaignCreate($options = array()){

        //Required Parameters
        $req_param = array('CampaignName');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'      => $this->getSessionID(),
                                         'Command'        => 'Campaign.Create',
                                         'ResponseFormat' => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Campaign name is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Update campaign details
     *
     * @param   array  $options = array(
     *                                 'CampaignID' => Campaign ID to update   {integer} (required)
     *                                 'CampaignStatus' => Set the cmapaign status {Draft | Ready | Sending | Paused | Pending Approval | Sent | Failed} (required)
     *                                 'CampaignName' => Name of the campaign  {string} (required)
     *                                 'RelEmailID' => The email content of campaign (email ID)  {integer} (required)
     *                                 'ScheduleType' => Type of the schedule {Not Scheduled | Immediate | Future | Recursive} (required)
     *                                 'SendDate' => Date to send campaign  {YYYY-MM-DD} (required)
     *                                 'SendTime' => Time to send campaign {HH: MM: SS} (required)
     *                                 'SendTimeZone' => Time zone of the schedule date  {string} (required)
     *                                 'ScheduleRecDaysOfWeek' => (Recursive scheduling) separate values with comma (,). Enter 0 for every day  {0 | 1 | 2 | 3 | 4 | 5 | 6 | 7} (required)
     *                                 'ScheduleRecDaysOfMonth' => (Recursive scheduling) separate with comma (,). Enter 0 for every day  {0 | 1 | 2 | 3 | 4 | 5 | ... | 31} (required)
     *                                 'ScheduleRecMonths' => (Recursive scheduling) separate with comma (,). Enter 0 for every month  {0 | 1 | 2 | ... | 12} (required)
     *                                 'ScheduleRecHours' => (Recursive scheduling) separate with comma (,)  {0 | 1 | 2 | ... | 23} (required)
     *                                 'ScheduleRecMinutes' => (Recursive scheduling) separate with comma (,)  {0 | 1 | 2 | ... | 59} (required)
     *                                 'ScheduleRecSendMaxInstance' => (Recursive scheduling) number of times to repeat campaign sending (enter 0 for unlimited)  {integer} (required)
     *                                 'ApprovalUserExplanation' => User explanation for the campaign if campaign is pending for approval  {string} (required)
     *                                 'GoogleAnalyticsDomains' => Domains to track with Google Analytics (seperate domains with line break (\n)) {string} (required)
     *                                 'RecipientListsAndSegments' => Target subscriber lists and segments. Each segment and list is seperated by comma. Format: ListID: SegmentID,ListID: SegmentID Ex: 3: 0,3: 2  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function campaignUpdate($options = array()){

        //Required Parameters
        $req_param = array('CampaignID');

        //Optinal Parameters
        $optl_param = array('CampaignStatus',
                           'CampaignName',
						   'RelEmailID',
                           'ScheduleType',
                           'SendDate',
                           'SendTime',
                           'SendTimeZone',
                           'ScheduleRecDaysOfWeek',
                           'ScheduleRecDaysOfMonth',
                           'ScheduleRecMonths',
                           'ScheduleRecHours',
                           'ScheduleRecMinutes',
                           'ScheduleRecSendMaxInstance',
                           'ApprovalUserExplanation',
                           'GoogleAnalyticsDomains',
                           'RecipientListsAndSegments');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'         => $this->getSessionID(),
                                         'Command'           => 'Campaign.Update',
                                         'ResponseFormat'    => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing campaign ID',
                                 '2' => 'Invalid campaign ID',
                                 '3' => 'Invalid campaign status value',
                                 '4' => 'Invalid email ID',
                                 '5' => 'Invalid campaign schedule type value',
                                 '6' => 'Missing send date',
                                 '7' => 'Missing send time',
                                 '8' => 'Day of month or day of week must be provided for recursive scheduling',
                                 '9' => 'Months must be provided for recursive scheduling',
                                 '10' => 'Hours must be provided for recursive scheduling',
                                 '11' => 'Minutes must be provided for recursive scheduling',
                                 '12' => 'Number of times to repeat must be provided for recursive scheduling',
                                 '13' => 'Time zone for scheduling is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Retrieves campaigns of a user.
     *
     * @param   array  $options = array(
     *                                 'OrderField' => Name of the field to order based on  {string} (required)
     *                                 'OrderType' => Ascending or descending ordering  {ASC | DESC} (required)
     *                                 'CampaignStatus' => Status of campaigns to retrieve {All | Draft | Ready | Sending | Paused | Pending Approval | Sent | Failed} (required)
     *                                 'SearchKeyword' => Keyword to look for in CampaignName field  {string} (required)
     *                                 'RecordsPerRequest' => How many rows to return per page = {integer} (required)
     *                                 'RecordsFrom' => Start from (starts from zero)  {integer} (required)
     *                                 'RetrieveTags' => Set to true if you are going to filter campaign list based on specific filters  {true|false} (v4.0.4+) (optional)
     *                                 'Tags' => Tag ID numbers separated with comma if you are going to filter campaign list based on tags {string} (v4.0.4+)  (optional)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *
     *                               //On Success
     *                               [Campaigns ] => Returns the list of all campaigns in array
     *                               [TotalCampaigns ] => Returns the total number of campaigns
     *                           )
     */
    public function campaignsGet($options = array()){

        //Required Parameters
        $req_param = array('OrderField',
                           'OrderType',
                           'CampaignStatus',
                           'RecordsPerRequest',
                           'RecordsFrom');

        //Optinal Parameters
        $optl_param = array('RetrieveTags',
                            'SearchKeyword',
                            'Tags');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'       => $this->getSessionID(),
                                         'Command'         => 'Campaigns.Get',
                                         'ResponseFormat'  => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing user ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Retrieve a speicifc campaign of a user.
     *
     * @param   array  $options = array(
     *                                 'CampaignID' => Campaign id  {integer} (required)
     *                                 'RetrieveStatistics' => If set to true, returns campaign statistics (with detailed information) {true | false} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *
     *                               //On Success
     *                               [Campaigns ] => Campaign information
     *                           )
     */
    public function campaignGet($options = array()){

        //Required Parameters
        $req_param = array('CampaignID',
                           'RetrieveStatistics');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'         => $this->getSessionID(),
                                         'Command'           => 'Campaign.Get',
                                         'ResponseFormat'    => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing campaign ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Deletes given campaigns
     *
     * @param   array  $options = array(
     *                                 'Campaigns' => Comma delimeted Campaign ids {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *                           )
     */
    public function campaignsDelete($options = array()){

        //Required Parameters
        $req_param = array('Campaigns');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'         => $this->getSessionID(),
                                         'Command'           => 'Campaigns.Delete',
                                         'ResponseFormat'    => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Campaign ids are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Returns the URL for the public archive page of campaigns
     *
     * @param   array  $options = array(
     *                                 'TagID' => Target tag ID to retrieve campaigns  {integer} (required)
     *                                 'TemplateURL' => URL of the public archive page template (optional) {string}
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [URL ] => URL for the public archive page
     *                           )
     */
    public function campaignsArchiveGetURL($options = array()){

        //Required Parameters
        $req_param = array('TagID');

        //Optinal Parameters
        $optl_param = array('TemplateURL');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'         => $this->getSessionID(),
                                         'Command'           => 'Campaigns.Archive.GetURL',
                                         'ResponseFormat'    => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Tag ID is missing',
                                 '2' => 'Tag does not exist',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /************************************************************
     *                  Clients Operation                       *
     *************************************************************/

    /**
     * Verifies and logs in the client account
     *
     * @param   array  $options = array(
     *                                 'Username' => Username of the client to be logged in {string} (required)
     *                                 'Password' => Password of the client to be logged in {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                                //On Success
     *                               [SessionID ] => SessionID of the logged in user
     *                           )
     */
    public function clientLogin($options = array()){

        //Required Parameters
        $req_param = array('Username',
                           'Password');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('Command'           => 'Client.Login',
                                         'ResponseFormat'    => 'JSON',
                                         'RememberMe'        => null), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Username is missing',
                                 '2' => 'Password is missing',
                                 '3' => 'Invalid login information',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result = $this->_callAPI($this->url, $post_params, $this->debug, false);

        //if Succes - Setting SessionID
        if($result->Success == 1){
            //Setting Session ID
            $this->setSessionID($result->SessionID);
        }

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);
    }


    /**
     * Sends the password reset email.
     *
     * @param   array  $options = array(
     *                                 'EmailAddress' => Email address of the client to be reminded {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function clientPasswordRemind($options = array()){

        //Required Parameters
        $req_param = array('EmailAddress');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('Command'           => 'Client.PasswordRemind',
                                         'ResponseFormat'    => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Email address is missing',
                                 '2' => 'Invalid email address',
                                 '3' => 'Email address not found in client database',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Resets clients password and sends the new password with email.
     *
     * @param   array  $options = array(
     *                                 'ClientID' => ID of the client whose password will be reset  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     *
     */
    public function clientPasswordReset($options = array()){

        //Required Parameters
        $req_param = array('ClientID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('Command'           => 'Client.PasswordReset',
                                         'ResponseFormat'    => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Client id is missing',
                                 '2' => 'Invalid user id',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Create new client account
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                              [ErrorText ] = > Error Text
     *                           )
     */
    public function clientCreate(){

       //Post Data
        $post_params = array('SessionID'         => $this->getSessionID(),
                             'Command'           => 'Client.Create',
                             'ResponseFormat'    => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Client name missing',
                                 '2' => 'Client username missing',
                                 '3' => 'Client password missing',
                                 '4' => 'Client email address missing',
                                 '5' => 'Email address format is invalid',
                                 '6' => 'Username already registered to another client',
                                 '7' => 'Email address already registered to another client',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;

    }


    /**
     * Update client account information
     *
     * @param   array  $options = array(
     *                                 'Access' => Determines the user type of api command. Default is user.  {user | client} (required)
     *                                 'ClientID' => ID number of the client account {integer} (required)
     *                                 'ClientUsername' => Username of the client account  {string} (required)
     *                                 'ClientPassword' => Password of the client account {string} (required)
     *                                 'ClientEmailAddress' => Email address of the client account  {string} (required)
     *                                 'ClientName' => Name of the client account {string} (required)
     *                                 'ClientAccountStatus' => Account status of the client {Enabled | Disabled} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                              [ErrorText ] = > Error Text
     *                           )
     */
    public function clientUpdate($options = array()){

        //Required Parameters
        $req_param = array('Access',
                           'ClientID',
                           'ClientUsername',
                           'ClientPassword',
                           'ClientEmailAddress',
                           'ClientName',
                           'ClientAccountStatus');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Client.Update',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Client name missing',
                                 '2' => 'Client username missing',
                                 '3' => 'Client password missing',
                                 '4' => 'Client email address missing',
                                 '5' => 'Invalid client account status',
                                 '6' => 'Client ID is missing',
                                 '7' => 'Email address format is invalid',
                                 '8' => 'Insufficient privileges for updating the client account',
                                 '9' => 'Username already registered to another client',
                                 '10'=> 'Email address already registered to another client',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves clients of logged in user.
     *
     * @param   array  $options = array(
     *                                 'OrderField' => Order field  {any client field} (required)
     *                                 'OrderTYPE' => Order type {ASC | DESC} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                              //On Success
     *                              [TotalClientCount  ] = > Total number of clietns user has
     *                              [Clients ] => Returned clients
     *                           )
     */
    public function clientsGet($options = array()){

        //Required Parameters
        $req_param = array('OrderField',
                           'OrderTYPE');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Clients.Get',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'order by field missing',
                                 '2' => 'sort type is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Deletes given clients.
     *
     * @param   array  $options = array(
     *                                 'Clients' => Comma delimeted Client ids  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                              [ErrorText ] = > Error Text
     *                           )
     */
    public function clientsDelete($options = array()){

        //Required Parameters
        $req_param = array('Clients');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Clients.Delete',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Client ids are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Assigns given campaigns to client.
     *
     * @param   array  $options = array(
     *                                 'ClientID' => ID of client {integer} (required)
     *                                 'CampaignIDs' => Comma seperated campaign ids to be assigned  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function clientAssignCampaigns($options = array()){

        //Required Parameters
        $req_param = array('ClientID',
                           'CampaignIDs');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Client.AssignCampaigns',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Client id is missing',
                                 '2' => 'Campaign ids are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;

    }

    /**
     * Assigns given subscriber lists to client.
     *
     * @param   array  $options = array(
     *                                 'ClientID' => ID of client {integer} (required)
     *                                 'SubscriberListIDs' => Comma seperated subscriber list ids to be assigned {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function clientAssignSubscriberLists($options = array()){

        //Required Parameters
        $req_param = array('ClientID',
                           'SubscriberListIDs');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Client.AssignSubscriberList',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Client id is missing',
                                 '2' => 'Subscriber list ids are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves assigned subscriber lists of logged in client.
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                                //On Success
     *                               [TotalListCount] => Total number of subscriber lists
     *                               [Lists ] => Returned lists
     *                           )
     *
     */
    public function clientListsGet(){

        //Post Data
        $post_params = array('SessionID'           => $this->getSessionID(),
                             'Command'             => 'Client.Lists.Get',
                             'ResponseFormat'      => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Retrieves subscriber list of logged in client.
     *
     * @param   array  $options = array(
     *                                 'ListID'  => ID of subscriber list to retrieve  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               // On Success
     *                               [Lists ] => Returned lists
     *                           )
     */
    public function clientListGet($options = array()){

        //Required Parameters
        $req_param = array('ListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Client.List.Get',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '2' => 'Client account does not have permission to view this lists information',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                  Custom Fields Operation                 *
     *************************************************************/


    /**
     * Creates a new custom field for given subscriber list.
     *
     * @param   array  $options = array(
     *                                 'SubscriberListID'  => ID of subscriber list  {integer} (required)
     *                                 'PresetName' => You can create a new custom field by selecting a preset. This way all options of the preset will be created by backend. (optional)
     *                                                 {Gender | Age | Employment | Income | Education | Days of the week | Months of the year | U.S. states | Continents | Satisfaction | Importance | Agreement | Comparison}
     *                                 'FieldName' => Name of new custom field  {string} (required)
     *                                 'FieldType' => Type of new custom field Single line | Paragraph text | Multiple choice | Drop down | Checkboxes | Hidden field} (required)
     *                                 'FieldDefaultValue' => Default value of new custom field  {string} (required)
     *                                 'OptionLabel' => label of nth option {string} (required)
     *                                 'OptionValue' => value of nth option {string} (required)
     *                                 'OptionSelected' => array of selected option ids (Option ids are n) {array} (required),
     *                                 'ValidationMethod' => Validation method of custom field {Disabled | Numbers | Letters | Numbers and letters | Email address | URL | Date | Time | Custom} (required)
     *                                 'ValidationRule' => Validation rule of custom field  {string} (required)
     *                                 'Visibility' => Whether to show custom field in subscriber area or not  {Public | User Only} (optional)
     *                                 'IsRequired' => Whether to make the custom field mandatory to fill in or not {Yes | No} (optional)
     *                                 'IsUnique' => Whether to force custom field entry to be unique or not  {Yes | No} (optional)
     *                                 'IsGlobal' => If this parameter is set to 'Yes', custom field will be valid for all lists in the user account {Yes | No} (optional)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [CustomFieldID  ] => ID of new custom field
     *                           )
     */
    public function customFieldCreate($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'FieldName',
                           'FieldType',
                           'ValidationMethod');

        //Optinal Parameters
        $optl_param = array('PresetName',
                            'FieldDefaultValue',
                            'OptionLabel',
                            'OptionValue',
                            'OptionSelected',
                            'ValidationRule',
                            'Visibility',
                            'IsRequired',
                            'IsUnique',
                            'IsGlobal');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'CustomField.Create',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '2' => 'Field name is missing',
                                 '3' => 'Field type is missing',
                                 '4' => 'Validation rule is missing',
                                 '5' => 'Invalid custom field preset name',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;

    }

    /**
     * Creates a new custom field for given subscriber list.
     *
     * @param   array  $options = array(
     *                                 'CustomFieldID'  => ID of custom field to be updated  {integer} (required)
     *                                 'FieldName' => Name of new custom field  {string} (required)
     *                                 'FieldType' => Type of new custom field Single line | Paragraph text | Multiple choice | Drop down | Checkboxes | Hidden field} (required)
     *                                 'FieldDefaultValue' => Default value of new custom field  {string} (required)
     *                                 'OptionLabel' => label of nth option {string} (required)
     *                                 'OptionValue' => value of nth option {string} (required)
     *                                 'OptionSelected' => array of selected option ids (Option ids are n) {array} (required),
     *                                 'ValidationMethod' => Validation method of custom field {Disabled | Numbers | Letters | Numbers and letters | Email address | URL | Date | Time | Custom} (required)
     *                                 'ValidationRule' => Validation rule of custom field  {string} (required)
     *                                 'Visibility' => Whether to show custom field in subscriber area or not  {Public | User Only} (optional)
     *                                 'IsRequired' => Whether to make the custom field mandatory to fill in or not {Yes | No} (optional)
     *                                 'IsUnique' => Whether to force custom field entry to be unique or not  {Yes | No} (optional)
     *                                 'IsGlobal' => If this parameter is set to 'Yes', custom field will be valid for all lists in the user account {Yes | No} (optional)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function customFieldUpdate($options = array()){

        //Required Parameters
        $req_param = array('CustomFieldID',
                           'FieldName',
                           'FieldType',
                           'FieldDefaultValue',
                           'OptionLabel',
                           'OptionValue',
                           'OptionSelected',
                           'ValidationMethod',
                           'ValidationRule');

        //Optinal Parameters
        $optl_param = array('Visibility',
                            'IsRequired',
                            'IsUnique',
                            'IsGlobal');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'CustomField.Update',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Custom field id is missing',
                                 '2' => 'Field name is missing',
                                 '3' => 'Field type is missing',
                                 '4' => 'Validation rule is missing',
                                 '6' => 'Custom field id is invalid',
                                 '7' => 'Field type is invalid',
                                 '8' => 'Validation method is invalid',
                                 '9' => 'Invalid visibility method',
                                 '10' => 'Invalid IsRequired value',
                                 '11' => 'Invalid IsUnique value',
                                 '12' => 'Invalid IsGlobal value',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Copies custom fields of a subscriber list to another subscriber list
     *
     * @param   array  $options = array(
     *                                 'SourceListID'  => ID of source subscriber list  {integer} (required)
     *                                 'TargetListID' => ID of target subscriber list  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function customFieldsCopy($options = array()){

        //Required Parameters
        $req_param = array('SourceListID',
                           'TargetListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'CustomFields.Copy',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Source subscriber list id is missing',
                                 '2' => 'Target subscriber list id is missing',
                                 '3' => 'Source subscriber list id is invalid',
                                 '4' => 'Target subscriber list id is invalid',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;

    }

    /**
     * Retrieves custom fields of given subscriber list
     *
     * @param   array  $options = array(
     *                                 'OrderField'  => Order field {field name of custom field} (required)
     *                                 'OrderType' => Order type {ASC | DESC} (required)
     *                                 'SubscriberListID' => Subscriber list id  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TotalCustomFields ] => Total number of custom fields of subscriber list
     *                               [CustomFields ] => Returned custom fields
     *                           )
     */
    public function customFieldsGet($options = array()){

        //Required Parameters
        $req_param = array('OrderField',
                           'OrderType',
                           'SubscriberListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'CustomFields.Get',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Deletes given custom fields
     *
     * @param   array  $options = array(
     *                                 'CustomFields'  => Comma delimeted custom field ids.  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     *
     */
    public function customFieldsDelete($options = array()){

        //Required Parameters
        $req_param = array('CustomFields');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'CustomFields.Delete',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Custom field ids are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                        Email Operation                   *
     *************************************************************/

    /**
     * Creates a blank email record for user.
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function emailCreate(){

        //Post Data
        $post_params = array('SessionID'           => $this->getSessionID(),
                             'Command'             => 'Email.Create',
                             'ResponseFormat'      => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Deletes given email.
     *
     * @param   array  $options = array(
     *                                 'EmailID '  => Id of email to be deleted.  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function emailDelete($options = array()){

        //Required Parameters
        $req_param = array('EmailID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.Delete',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Email id is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves all information of given email.
     *
     * @param   array  $options = array(
     *                                 'EmailID '  => Id of email {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function emailGet($options = array()){

        //Required Parameters
        $req_param = array('EmailID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.Get',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Email id is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Returns the list of email contents created so far
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TotalEmailCount ] => Number of email contents returned
     *                               [Emails ] => List of emails in array format
     *                           )
     */
    public function emailsGet(){

        //Post Data
        $post_params = array('SessionID'           => $this->getSessionID(),
                             'Command'             => 'Emails.Get',
                             'ResponseFormat'      => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Updates emails custom field information.
     *
     * @param   array  $options = array(
     *                                 'ValidateScope'  => Defines the validation scope of the email (required fields, links, etc.)  {OptIn | Campaign | AutoResponder} (required)
     *                                 'EmailID' => Id of the email to be updated {integer} (required)
     *                                 'EmailName' => Name of the email {string} (required)
     *                                 'FromName' => From name of the email  {string} (required)
     *                                 'FromEmail' => From email address of the email {string} (required)
     *                                 'ReplyToName' => Reply to name of the email {string} (required)
     *                                 'Mode' => Email's content mode  {Empty | Template | Import} (required)
     *                                 'FetchURL' => Email's remote content url  {string} (required)
     *                                 'Subject' => Email's subject  {string} (required)
     *                                 'PlainContent' => Email's plain content {string} (required)
     *                                 'HTMLContent' => Email's html content  {string} (required)
     *                                 'ImageEmbedding' => Email's image embedding  {Enabled | Disabled} (required)
     *                                 'RelTemplateID' => Email's template id {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function emailUpdate($options = array()){

        //Required Parameters
        $req_param = array('ValidateScope',
                           'EmailID',
                           );

        //Optinal Parameters
        $optl_param = array('EmailName',
                            'FromName',
						    'FromEmail',
                            'ReplyToName',
                            'ReplyToEmail',
                            'Mode',
                            'Subject',
                            'PlainContent',
                            'HTMLContent',
                            'ImageEmbedding',
						    'FetchURL',
							'RelTemplateID');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.Update',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Email id is missing',
                                 '2' => 'FetchURL is missing',
                                 '3' => 'Email id is invalid',
                                 '4' => 'Mode is invalid',
                                 '5' => 'RelTemplateID is missing',
                                 '6' => 'FromEmail email address is invalid',
                                 '7' => 'ReplyToEmail email address is invalid',
                                 '8' => 'Plain and HTML content is empty',
                                 '9' => 'Missing validation scope parameter',
                                 '10' => 'Invalid validation scope parameter',
                                 '11' => 'Missing unsubscription link in HTML content',
                                 '12' => 'Missing unsubscription link in plain content',
                                 '13' => 'Missing opt-in confirmation link in HTML content',
                                 '14' => 'Missing opt-in confirmation link in plain content',
                                 '15' => 'Missing opt-in reject link in HTML content',
                                 '16' => 'Missing opt-in reject link in plain content',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Creates a new email content based on existing one
     *
     * @param   array  $options = array(
     *                                 'EmailID'  => The ID number of email content which is going to be duplicated  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [EmailID ] => ID number of the new email
     *                               [EmailName ] => Name of the new email
     *                           )
     */
    public function emailDuplicate($options = array()){

        //Required Parameters
        $req_param = array('EmailID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.Duplicate',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Email ID is missing',
                                 '2' => 'Email not found',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Returns the list of personalization tags
     *
     * @param   array  $options = array(
     *                                 'ListID'  => If the Scope is 'Subscriber', list ID must be provided. It can be an integer single list ID or multiple list IDs in array  {integer | array} (required)
     *                                 'Scope' => Types of tags to return. Possible array values: 'Subscriber', 'CampaignLinks', 'OptLinks', 'ListLinks', 'AllLinks', 'User'  {array} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function emailPersonalizationTags($options = array()){

        //Required Parameters
        $req_param = array('ListID',
                           'Scope');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.PersonalizationTags',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Scope is missing',
                                 '2' => 'List ID is missing',
                                 '3' => 'List ID does not belong to the logged in user',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Sends a preview email to the provided email address
     *
     * @param   array  $options = array(
     *                                 'EmailID'  => Email ID number  {integer} (required)
     *                                 'EmailAddress' => Email address to send preview of the email  {string} (required)
     *                                 'ListID' => List ID number {integer} (required)
     *                                 'CampaignID' => Campaign ID number {integer} (required)
     *                                 'AddUserGroupHeaderFooter' => If set to false, user group header and footer is not inserted into email. This is only required for opt-in confirmation emails. {true | false}
     *                                                              (default: true, required, v4.1.18+) (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function emailEmailPreview($options = array()){

        //Required Parameters
        $req_param = array('EmailID',
                           'EmailAddress',
                           'ListID',
                           'CampaignID',
                           'AddUserGroupHeaderFooter');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.EmailPreview',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing email ID',
                                 '2' => 'Missing email address',
                                 '3' => 'Invalid email ID',
                                 '4' => 'Invalid email address format',
                                 '5' => 'Missing list ID',
                                 '6' => 'Invalid list ID',
                                 '7' => 'Invalid campaign ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Returns SpamAssassin spam filter test results of your email
     *
     * @param   array  $options = array(
     *                                 'EmailID'  => Email ID number  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TestResults ] => Test results will be returned in JSON array format
     *                           )
     *
     */
    public function emailSpamTest($options = array()){

        //Required Parameters
        $req_param = array('EmailID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.SpamTest',
                                         'ResponseFormat'      => 'JSON'), $options);
        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing email ID',
                                 '2' => 'Invalid email ID',
                                 '3' => 'License error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Submits the email to design preview service. This service will show you how your email design looks like in
     * various email clients such as Outlook, Outlook Express, Thunderbird, Gmail, Yahoo!, AOL, Lotus Notes, etc.
     *
     * @param   array  $options = array(
     *                                 'EmailID'  => Email ID number  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               // On Success
     *                               [JobID  ] => Internal design preview job ID
     *                               [PreviewMyEmailJobID ] => PreviewMyEmail.com job ID
     *                           )
     */
    public function emailDesignPreviewCreate($options = array()){

        //Required Parameters
        $req_param = array('EmailID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.DesignPreview.Create',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing email ID',
                                 '2' => 'Invalid email ID',
                                 '3' => 'Connection error occurred',
                                 '4' => 'Your email couldnt be previewed. Please contact administrator. Error code: PME023',
                                 '5' => 'PreviewMyEmail.com account is out of credits',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Deletes the request from design preview service.
     *
     * @param   array  $options = array(
     *                                 'EmailID'  => Email ID number  {integer} (required)
     *                                 'JobID' => Job ID number {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     *
     */
    public function emailDesignPreviewDelete($options = array()){

        //Required Parameters
        $req_param = array('EmailID',
                           'JobID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.DesignPreview.Delete',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing email ID',
                                 '2' => 'Missing job ID',
                                 '3' => 'Invalid email ID',
                                 '4' => 'Invalid job ID',
                                 '5' => 'Connection error occurred',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Returns the list of previously created preview requests
     *
     * @param   array  $options = array(
     *                                 'EmailID'  => Owner email ID number {JSON | XML} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                                //On Success
     *                               [PreviewList ] => List of previews in array
     *                           )
     */
    public function emailDesignPreviewGetList($options = array()){

        //Required Parameters
        $req_param = array('EmailID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.DesignPreview.GetList',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing email ID',
                                 '2' => 'Invalid email ID',
                                 '3' => 'Connection error occurred',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Returns the details of the design preview request
     *
     * @param   array  $options = array(
     *                                 'EmailID'  => Owner email ID number  {integer} (required)
     *                                 'JobID' => Design preview job ID number  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [PreviewRequest  ] => Details of the preview request in array
     *                           )
     */
    public function emailDesignPreviewDetails($options = array()){

        //Required Parameters
        $req_param = array('EmailID',
                           'JobID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.DesignPreview.Details',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing email ID',
                                 '2' => 'Invalid email ID',
                                 '3' => 'Missing job ID',
                                 '4' => 'Invalid job ID',
                                 '5' => 'Connection error occurred',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Returns the available credits in your account
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [AvailableCredits   ] => Number of available credits (returns an integer or "Unlimited")
     *                           )
     */
    public function emailDesignPreviewGetCredits(){

        //Post Data
        $post_params = array('SessionID'           => $this->getSessionID(),
                             'Command'             => 'Email.DesignPreview.GetCredits',
                             'ResponseFormat'      => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Not available',
                                 '2' => 'Connection error occurred',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Creates a new email template
     *
     * @param   array  $options = array(
     *                                 'RelOwnerUserID'  =>  Assigned user (set to 0 to make visible to anyone) {integer} (required)
     *                                 'TemplateName' => Name of the email template  {string} (required)
     *                                 'TemplateDescription' => Description of the email template  {string} (required)
     *                                 'TemplateSubject' => Subject of the email template {string} (required)
     *                                 'TemplateHTMLContent' => HTML content of the email template {string} (required)
     *                                 'TemplatePlainContent' => Plain content of the email template  {string} (required)
     *                                 'TemplateThumbnailPath' => Thumbnail path of the template on the server {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TemplateID ] => ID of the new email template
     *                           )
     *
     */
    public function emailTemplateCreate($options = array()){

        //Required Parameters
        $req_param = array('RelOwnerUserID',
                           'TemplateName',
                           'TemplateDescription',
                           'TemplateSubject',
                           'TemplateHTMLContent',
                           'TemplatePlainContent',
                           'TemplateThumbnailPath');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.Template.Create',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Template name is missing',
                                 '2' => 'At least one of the email content types must be provided (HTML, plain or both)',
                                 '3' => 'Target user is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves email template requested by the user
     *
     * @param   array  $options = array(
     *                                 'TemplateID'  =>  ID of the requested template  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                                //On Success
     *                               [Template  ] => list of available templates
     *                           )
     */
    public function emailTemplateGet($options = array()){

        //Required Parameters
        $req_param = array('TemplateID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.Template.Get',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Template ID is missing',
                                 '2' => 'Template ID is invalid',
                                 '3' => 'Template does not belong to this user',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves email templates defined in the system
     *
     * @param   array  $options = array(
     *                                 'UserID'  =>  Templates of a specific user (administrator only) {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               // On Success
     *                               [Templates   ] => list of available templates
     *                           )
     */
    public function emailTemplatesGet($options = array()){

        //Required Parameters
        $req_param = array('UserID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'           => $this->getSessionID(),
                                         'Command'             => 'Email.Template.Get',
                                         'ResponseFormat'      => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Updates email template
     *
     * @param   array  $options = array(
     *                                 'TemplateID'  =>  Template ID to update  {string} (required)
     *                                 'TemplateName' => Name of the email template  {string} (required)
     *                                 'TemplateDescription' => Description of the email template  {string} (required)
     *                                 'TemplateSubject' => Subject of the email template  {string} (required)
     *                                 'TemplateHTMLContent' => HTML content of the email template   {string} (required)
     *                                 'TemplatePlainContent' => Plain content of the email template {string} (required)
     *                                 'TemplateThumbnailPath' => Thumbnail path of the template on the server, {string} (required)
     *                                 'RelOwnerUserID' => Owner user ID of the template  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     *
     */
    public function emailTemplateUpdate($options = array()){

        //Required Parameters
        $req_param = array('TemplateID',
                           'TemplateName',
                           'TemplateDescription',
                           'TemplateSubject',
                           'TemplateHTMLContent',
                           'TemplatePlainContent',
                           'TemplateThumbnailPath',
                           'RelOwnerUserID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'Email.Template.Update',
                                         'ResponseFormat'        => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Template ID is missing',
                                 '2' => 'Invalid template ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Delete email templates
     *
    * @param   array  $options = array(
     *                                 'Templates'  =>  Template IDs separated by comma for deleting {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function emailTemplateDelete($options = array()){

        //Required Parameters
        $req_param = array('Templates');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'Email.Template.Delete',
                                         'ResponseFormat'        => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Template IDs are missing',
                                 '2' => 'Templates suitable for deleting not found',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                       List Integration                   *
     *************************************************************/

    /**
     * Adds a web service url for list subscription or unsubscription events.
     *
    * @param   array  $options = array(
     *                                 'SubscriberListID '  =>  ID of subscriber list  {integer} (required)
     *                                 'Event' => Event of the integration  {subscription | unsubscription} (required)
     *                                 'URL' => URL of the web service  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [WebServiceIntegrationID ]  => ID of new web service integration url
     *                           )
     */
    public function listIntegrationAddURL($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'Event',
                           'URL');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'ListIntegration.AddURL',
                                         'ResponseFormat'        => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '2' => 'URL is missing',
                                 '3' => 'Event type is missing',
                                 '4' => 'Invalid subscriber list id',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves web service integration URLs of a subscriber list.
     *
     * @param   array  $options = array(
     *                                 'SubscriberListID '  =>  ID of subscriber list  {integer} (required)
     *                                 'Event' => Event of the integration  {subscription | unsubscription} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [URLs  ]  => Web service integration urls
     *                           )
     */
    public function listIntegrationGetURLs($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'Event');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'ListIntegration.GetURLs',
                                         'ResponseFormat'        => 'JSON'), $options);
        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '2' => 'Event type is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Tests an URL.
     *
     * @param   array  $options = array(
     *                                 'URL'  =>  URL address to be tested {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function listIntegrationTestURL($options = array()){

        //Required Parameters
        $req_param = array('URL');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'ListIntegration.TestURL',
                                         'ResponseFormat'        => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Deletes given web service urls.
     *
     * @param   array  $options = array(
     *                                 'URLs'  => Comma delimeted url ids. {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function listIntegrationDeleteURLs($options = array()){

        //Required Parameters
        $req_param = array('URLs');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'ListIntegration.DeleteURLs',
                                         'ResponseFormat'        => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Web service integration url ids are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Generates subscription form html code.
     *
     * @param   array  $options = array(
     *                                 'SubscriberListID '  => Subscriber list id.  {integer} (required)
     *                                 'CustomFields' => Comma delimeted custom field ids.  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [HTMLCode ] => Subscription form html code
     *                           )
     */
    public function listIntegrationGenerateSubscriptionFormHTMLCode($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'CustomFields');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'ListIntegration.GenerateSubscriptionFormHTMLCode',
                                         'ResponseFormat'        => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Generates unsubscription form html code.
     *
     * @param   array  $options = array(
     *                                 'SubscriberListID '  => Subscriber list id.  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [HTMLCode ] => Unsubscription form html code
     *                           )
     */
    public function listIntegrationGenerateUnsubscriptionFormHTMLCode($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'ListIntegration.GenerateSubscriptionFormHTMLCode',
                                         'ResponseFormat'        => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                         List Operation                   *
     *************************************************************/

    /**
     * Creates a new subscriber list
     *
     * @param   array  $options = array(
     *                                 'SubscriberListName'  => Name of the subscriber list to be created. {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [ListID  ] => ID of the new subscriber list
     *                           )
     */
    public function listCreate($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListName');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'             => $this->getSessionID(),
                                         'Command'               => 'List.Create',
                                         'ResponseFormat'        => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list name is missing',
                                 '2' => 'There is already a subscriber list with given name',
                                 '3' => 'Allowed list amount exceeded',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Updates subscriber list information.
     *
     * @param   array  $options = array(
     *                                 'SubscriberListID'  => ID of the subscriber list to be updated.  {integer} (required)
     *                                 'Name' => name of the subscriber list.  {string} (required)
     *                                 'OptInMode' => Opt-in mode of the subscriber list. {Single | Double} (required)
     *                                 'OptInConfirmationEmailID' => ID number of the email which will be used for sending opt-in confirmation email (required if opt-in mode is set to double)  {integer} (required)
     *                                 'OptOutScope' => Opt-out scope of the subscriber list.  {This list | All lists} (required)
     *                                 'OptOutAddToSuppressionList' => Set 'Yes' to add unsubscribed email address into the suppression list  {Yes | No} (required)
     *                                 'SendServiceIntegrationFailedNotification' => If set to true, a notification email will be sent to list owner when a web service integration fails. {true | false} (required)
     *                                 'SubscriptionConfirmationPendingPageURL' => URL of the subscription confirmation pending page. This page is showed only in double opt-in lists.  {string} (required)
     *                                 'SubscriptionConfirmedPageURL' => URL of the subscription confirmed page.  {string} (required)
     *                                 'HideInSubscriberArea' => If set to true, subscriber list will not be shown in subscriber area.  {true | false} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function listUpdate($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID');

        //Optinal Parameters
        $optl_param = array('Name',
                           'OptInMode',
                           'OptInConfirmationEmailID',
                           'OptOutScope',
                           'OptOutAddToSuppressionList',
                           'OptOutAddToGlobalSuppressionList',
                           'SendServiceIntegrationFailedNotification',
                           'SubscriptionConfirmationPendingPageURL',
                           'SubscriptionConfirmedPageURL',
                           'HideInSubscriberArea',
                           'UnsubscriptionConfirmedPageURLEnabled',
                           'UnsubscriptionConfirmedPageURL');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'List.Update',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list name is missing',
                                 '2' => 'There is already a subscriber list with given name',
                                 '3' => 'Allowed list amount exceeded',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Retrieves subscriber lists of logged in user.
     *
     * @param   array  $options = array(
     *                                 'OrderField'  => OrderField  {field name of subscriber list} (required)
     *                                 'Name' => Order type  {ASC | DESC} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TotalListCount ] => Total number of subscriber lists
     *                               [Lists ] => Returned lists
     *                           )
     */
    public function listsGet($options = array()){

        //Required Parameters
        $req_param = array('OrderField',
                           'OrderType');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Lists.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Retrieves subscriber list.
     *
     * @param   array  $options = array(
     *                                 'ListID'  => ID of subscriber list to retrieve  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [List ] => Returned list information
     *                           )
     */
    public function listGet($options = array()){

        //Required Parameters
        $req_param = array('ListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'List.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list id is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Deletes given subscriber lists.
     *
     * @param   array  $options = array(
     *                                 'Lists'  => Comma delimeted subscriber list ids.  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function listsDelete($options = array()){

        //Required Parameters
        $req_param = array('Lists');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Lists.Delete',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list ids are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                         Media  Operation                  *
     *************************************************************/

    /**
     * Uploads media to the media library
     *
     * @param   array  $options = array(
     *                                 'MediaData'  => Media file contents encoded with base64  {string} (required)
     *                                 'MediaType' => MIME type of the media. Ex: image/gif  {string} (required)
     *                                 'MediaSize' => File size of the media file in bytes  {integer} (required)
     *                                 'MediaName' => File name of the media file {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [MediaID ] => ID of the new subscriber list
     *                           )
     */
    public function mediaUpload($options = null){

        //Required Parameters
        $req_param = array('MediaData',
                           'MediaType',
                           'MediaSize',
                           'MediaName');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Media.Upload',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing media data',
                                 '2' => 'Missing media type',
                                 '3' => 'Missing media size',
                                 '4' => 'Media file exceeds allowed size',
                                 '5' => 'Missing media name',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Returns the list of media available in the media library
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TotalMedia  ] => Total number of media files in the library
     *                               [MediaFiles  ] => List of media files in the library
     *                           )
     *
     */
    public function mediaBrowse(){

        //Post Data
        $post_params = array('SessionID'                  => $this->getSessionID(),
                             'Command'                    => 'Media.Browse',
                             'ResponseFormat'             => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves details of a media item
     *
     * @param   array  $options = array(
     *                                 'MediaID '  => Media ID of the media item  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function mediaRetrieve($options = array()){

        //Required Parameters
        $req_param = array('MediaID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Media.Retrieve',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing media ID',
                                 '2' => 'Invalid media ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Delete a media item
     *
     * @param   array  $options = array(
     *                                 'MediaID '  => Media ID of the media item  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     *
     */
    public function mediaDelete($options = array()){

        //Required Parameters
        $req_param = array('MediaID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Media.Delete',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing media ID',
                                 '2' => 'Invalid media ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                     Segement  Operation                  *
     *************************************************************/

    /**
     * Create a new segment under a subscriber list
     *
     * @param   array  $options = array(
     *                                 'SubscriberListID'  => Subscriber list ID to create segment under {integer} (required)
     *                                 'SegmentName' => Name of the new segment  {string} (required)
     *                                 'SegmentOperator' => Match all or any rules {and | or} (required)
     *                                 'SegmentRuleField' => Array of segment rule fields {array} (required)
     *                                 'SegmentRuleFilter' => Array of filters values for each segment rule field defined {array} (required)
     *                                 'SegmentRuleOperator' => Array of operators for each segment rule field defined {array} (required)
     *										Valid operators are
     *                                 		'Is', 'Is not', 'Contains', 'Does not contain', 'Begins with', 'Ends with', 'Is set', 'Is not set'
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *                           )
     */
    public function segmentCreate($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'SegmentName',
                           'SegmentOperator',
						   'SegmentRuleField',
						   'SegmentRuleFilter',
						   'SegmentRuleOperator');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Segment.Create',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing subscriber list ID',
                                 '2' => 'Missing segment name',
                                 '3' => 'Missing segment operator',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Updates Segment.
     *
     * @param   array  $options = array(
     *                                 'SegmentID'  => Subscriber list ID to create segment under {integer} (required)
     *                                 'SegmentName' => Name of the new segment  {string} (required)
     *                                 'SegmentOperator' => Match all or any rules {and | or} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *                           )
     *
     */
    public function segmentUpdate($options = array()){

        //Required Parameters
        $req_param = array('SegmentID',
                           'SegmentName',
                           'SegmentOperator');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Segment.Update',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing segment id',
                                 '2' => 'Missing segment name',
                                 '3' => 'Missing segment operator',
                                 '4' => 'Invalid segment id',
                                 '5' => 'Invalid segment operator',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves segments of a subscriber list.
     *
     * @param   array  $options = array(
     *                                 'OrderField'  => Field name to order rows {string} (required)
     *                                 'OrderType' => Ascending or descending sorting  {ASC | DESC} (required)
     *                                 'SubscriberListID' => Subscriber list ID to return segments {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *
     *                               //On Success
     *                               [TotalSegments ] => Number of segments returned
     *                               [Segments ] => Returned segment rows
     *                           )
     */
    public function segmentGet($options = array()){

        //Required Parameters
        $req_param = array('OrderField',
                           'OrderType',
                           'SubscriberListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Segments.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing subscriber list id',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Deletes given segments.
     *
     * @param   array  $options = array(
     *                                 'Segments'  => Comma delimeted Segment ids  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *                           )
     */
    public function segmentsDelete($options = array()){

        //Required Parameters
        $req_param = array('Segments');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Segments.Delete',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Segment ids are missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Copies segments from a selected subscriber list.
     *
     * @param   array  $options = array(
     *                                 'SourceListID '  => Copy segments from subscriber list ID  {integer} (required)
     *                                 'TargetListID ' => Copy segments to subscriber list ID {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *                           )
     *
     */
    public function segmentsCopy($options = array()){

        //Required Parameters
        $req_param = array('SourceListID',
                           'TargetListID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Segments.Copy',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing source subscriber list id',
                                 '2' => 'Missing target subscriber list id',
                                 '3' => 'Invalid source subscriber list id',
                                 '4' => 'Invalid target subscriber list id',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                  Subscribers Operation                   *
     *************************************************************/

    /**
     * Logs in the subscriber
     *
     * @param   array  $options = array(
     *                                 'ListID'  => List ID that stores the subscriber information  {integer} (required)
     *                                 'MSubscriberID' => ID of the subscriber (md5) {integer} (required)
     *                                 'MEmailAddress' => Email address of the subscriber (md5){string} (required)
     *                                 'EmailAddress' => Email address to validate  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function subscriberLogin($options = array()){

        //Required Parameters
        $req_param = array('ListID',
                           'MSubscriberID',
                           'MEmailAddress',
                           'EmailAddress');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('Command'                    => 'Subscriber.Login',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing list ID',
                                 '2' => 'Missing subscriber ID',
                                 '3' => 'Missing subscriber email address',
                                 '4' => 'Missing validation email address',
                                 '5' => 'Invalid list ID',
                                 '6' => 'Invalid user',
                                 '7' => 'Invalid validation email address',
                                 '8' => 'Invalid login information',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //if Succes - Setting SessionID
        if($result->Success == 1){
            //Setting Session ID
            $this->setSessionID($result->SessionID);
        }

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Returns subscriber lists of the user
     *
     * '''Important:''' This command requires subscriber login session which can be retrieved by running [[Subscriber.Login]] API call.
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               // On Success
     *                               [SubscribedLists ] => Returns the list of subscriber lists and subscriptions in array
     *                           )
     */
    public function subscriberGetLists(){

        //Post Data
        $post_params = array('SessionID'                  => $this->getSessionID(),
                             'Command'                    => 'Subscriber.GetLists',
                             'ResponseFormat'             => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Invalid authentication #1',
                                 '2' => 'Invalid authentication #2',
                                 '3' => 'Invalid subscriber information',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieve subscriber information
     *
     * @param   array  $options = array(
     *                                 'EmailAddress'  => Email address of the target subscriber  {string} (required)
     *                                 'ListID' => ID of the list which email address is subscribed to {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [SubscriberInformation  ] => Returns the subscriber information
     *                           )
     */
    public function subscriberGet($options = array()){

        //Required Parameters
        $req_param = array('EmailAddress',
                           'ListID');

        //Optinal Parameters
        $optl_param = array('Suppressed');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscriber.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing email address',
                                 '2' => 'Missing subscriber list ID',
                                 '3' => 'Subscriber doesnt exist',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves subscribers of a subscriber list.
     *
     * @param   array  $options = array(
     *                                 'OrderField'  => Name of the field to order based on  {string} (required)
     *                                 'OrderType' => Ascending or descending ordering {ASC | DESC} (required)
     *                                 'RecordsFrom' => Start from (starts from zero)  {integer} (required)
     *                                 'RecordsPerRequest' => How many rows to return per page {integer} (required)
     *                                 'SearchField' => Subscriber list field to make the search. Leave empty to disable search {string} (required)
     *                                 'SearchKeyword' => The keyword to search in subscriber list database. Leave empty to disable search  {string} (required)
     *                                 'SubscriberListID' => List ID of the subscribers  {integer} (required)
     *                                 'SubscriberSegment' => Target segment ID or one of the following values: Suppressed, Unsubscribed, Soft bounced, Hard bounced, Opt-in pending, Active.  {mixed} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText ] => Error Text
     *
     *                               //On Success
     *                               [Subscribers   ] => Returns the list of all subscribers in array
     *                               [TotalSubscribers ] => Returns the total number of subscribers
     *                           )
     */
    public function subscribersGet($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
						   'SubscriberSegment');

        //Optinal Parameters
        $optl_param = array('OrderField',
                           'OrderType',
                           'RecordsFrom',
                           'RecordsPerRequest',
                           'SearchField',
                           'SearchKeyword',);

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscribers.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing subscriber list ID',
                                 '2' => 'Target segment ID is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Import the provided subscriber data into subscriber list
     *
     * @param   array  $options = array(
     *                                 'ListID'  => Target subscriber list ID {integer} (required)
     *                                 'ImportStep' => 1 for passing import data, 2 for field mapping  {integer} (required)
     *                                 'ImportID' => ID number of the import process (step 2) {integer} (required)
     *                                 'ImportType' => Type of the import (step 1)  {Copy | File | MySQL} (required)
     *                                 'ImportData' => CSV file contents (step 1)  {string} (required)
     *                                 'ImportFileName' => Uploaded file name (step 1)  {string} (required)
     *                                 'FieldTerminator' => Set the field terminator for CSV import. Example: ,  {string} (optional, >= v4.1.0)
     *                                 'FieldEncloser' => Set the field encloser for CSV import. Example: ' {string} (optional, >= v4.1.0)
     *                                 'ImportMySQLHost' => MySQL host (step 1) {string} (required)
     *                                 'ImportMySQLPort' => MySQL port (step 1) {string} (required)
     *                                 'ImportMySQLUsername' => MySQL username (step 1)  {string} (required)
     *                                 'ImportMySQLPassword' => MySQL password (step 1) {string} (required)
     *                                 'ImportMySQLDatabase' => MySQL database (step 1)  {string} (required)
     *                                 'ImportMySQLQuery' => MySQL SQL query to execute (step 1)  {string} (required)
     *                                 'AddToGlobalSuppressionList' => Defines whether import must done for global suppression list or not (step 1)  {true | false} (required)
     *                                 'AddToSuppressionList' => Defines whether import must done for suppression list or not (step 1)  {true | false} (required)
     *                                 'MappedFields' => Mapped fields (step 2) {string} (required)
     *                                 'TriggerBehaviors' => Trigger Auto Responders and Web Services on import {true | false}
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [ImportID  ] => ID number of the import data
     *                               [ImportFields    ] => List of fields included in the import data (step 1)
     *                               [AllowedMaxSize  ] => Maximum allowed file size for import file upload. Returned if error #18 occurs (step 1)
     *                               [TotalData ] => Number of rows in the import data (step 2)
     *                               [TotalImported ] => Number of emails imported (step 2)
     *                               [TotalDuplicates ] => Number of emails already exists and ignored (step 2)
     *                               [TotalFailed ] => Number of emails failed to import (step 2)
     *                           )
     *
     */
    public function subscribersImport($options = array()){

        //Required Parameters
        $req_param = array('ListID',
                           'ImportStep');

        //Optinal Parameters
        $optl_param = array('FieldTerminator',
                            'FieldEncloser',
                           'ImportType',
                           'ImportID',
                           'ImportData',
                           'ImportFileName',
                           'ImportMySQLHost',
                           'ImportMySQLPort',
                           'ImportMySQLUsername',
                           'ImportMySQLPassword',
                           'ImportMySQLDatabase',
                           'ImportMySQLQuery',
                           'AddToGlobalSuppressionList',
                           'AddToSuppressionList',
                           'MappedFields',
						   'TriggerBehaviors'
							);

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscribers.Import',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Import type not set',
                                 '2' => 'Import data not provided',
                                 '3' => 'Import step not valid',
                                 '4' => 'Subscriber list ID is invalid',
                                 '5' => 'CSV data in not supported format',
                                 '6' => 'Import process already completed',
                                 '7' => 'Email address field mapped with multiple fields',
                                 '8' => 'Email address not mapped with any corresponding fields',
                                 '9' => 'Uploaded file is missing',
                                 '10' => 'MySQL host is missing',
                                 '11' => 'MySQL port is missing',
                                 '12' => 'MySQL database is missing',
                                 '13' => 'File does not exist',
                                 '14' => 'MySQL query is missing',
                                 '15' => 'MySQL connection or database name is incorrect. Not working.',
                                 '16' => 'MySQL query has errors',
                                 '17' => 'Import type not supported',
                                 '18' => 'Uploaded file size exceeds allowed maximum file size.',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, true);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Subscribes an email address to provided subscriber list or lists
     *
     * @param   array  $options = array(
     *                                 'ListID'  => Target List ID  {integer} (required)
     *                                 'EmailAddress' => Email address to subscribe {true | false} (required)
     *                                 'CustomFieldX' => Additional information about the subscriber. Replace X with the ID number of the custom field.  {array} (required)
     *                                 'IPAddress' => IP address of subscriber  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [SubscriberID ] => Subscriber ID of the email address will be returned if the result is success
     *                               [RedirectURL ] => Target URL to redirect user after the process,
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorCustomFieldID ] => If there's an error with one of the provided custom fields, custom field ID number will be provided
     *                               [ErrorCustomFieldTitle ] => If there's an error with one of the provided custom fields, custom field title will be provided
     *                               [ErrorCustomFieldDescription ] => If there's an error with one of the provided custom fields, description of the error message is provided
     *                           )
     */
    public function subscriberSubscribe($options = array()){

        //Required Parameters
        $req_param = array('ListID',
                           'EmailAddress',
                           'Fields',
                           'IPAddress');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscriber.Subscribe',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Target subscriber list ID is missing',
                                 '2' => 'Email address is missing',
                                 '3' => 'IP address of subscriber is missing',
                                 '4' => 'Invalid subscriber list ID',
                                 '5' => 'Invalid email address',
                                 '6' => 'One of the provided custom fields is empty. Custom field ID and title is provided as an additional output parameter',
                                 '7' => 'One of the provided custom field value already exists in the database. Please enter a different value. Custom field ID and title is provided as an additional output parameter',
                                 '8' => 'One of the provided custom field value failed in validation checking. Custom field ID and title is provided as an additional output parameter',
                                 '9' => 'Email address already exists in the list',
                                 '10' => 'Unknown error occurred',
                                 '11' => 'Invalid user information',
                                 '18' => 'Uploaded file size exceeds allowed maximum file size.',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Perform opt-in confirmation or reject
     *
     * @param   array  $options = array(
     *                                 'ListID'  => Subscriber List ID  {integer} (required)
     *                                 'SubscriberID' => Subscriber ID  {integer} (required)
     *                                 'Mode' => Performs opt-in confirmation or reject {Confirm | Reject} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ProcessedMode  ] => returns the process completed
     *                               [RedirectURL ] => Target URL to redirect user after the process,
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function subscriberOptIn($options = array()){

        //Required Parameters
        $req_param = array('ListID',
                           'SubscriberID',
                           'Mode');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscriber.OptIn',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list ID is missing',
                                 '2' => 'Subscriber ID is missing',
                                 '3' => 'Opt mode is missing',
                                 '4' => 'Invalid subscriber list ID',
                                 '5' => 'Invalid user ID',
                                 '6' => 'Invalid subscriber ID',
                                 '7' => 'Invalid opt-in process mode',
                                 '8' => 'Subscriber already opt-in confirmed',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Unsubscribe the subscriber from the list
     *
     * @param   array  $options = array(
     *                                 'ListID'  => Subscriber List ID {integer} (required)
     *                                 'CampaignID' => If link is generated for an email campaign, campaign ID should be provided for statistics {integer} (required)
     *                                 'EmailID' => If email ID is provided, the unsubscription statistics will be registered to that email and owner A/B split testing campaign {integer} (optional)
     *                                 'SubscriberID' => Subscriber ID must be provided  {integer} (required)
     *                                 'EmailAddress' => Email address must be provided {string} (required)
     *                                 'IPAddress' => IP address of the user who has requested to unsubscribe  {string} (required)
     *                                 'Preview' => If set to 1, unsubscription process will be simulated {1 | 0} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [RedirectURL ] => Target URL to redirect user after the process,
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function subscriberUnsubscribe($options = array()){

        //Required Parameters
        $req_param = array('ListID',
                           //'CampaignID',
                           'SubscriberID',
                           'EmailAddress',
                           'IPAddress',
                           'Preview');

        //Optinal Parameters
        $optl_param = array('EmailID', 'CampaignID');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscriber.Unsubscribe',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Subscriber list ID is missing',
                                 '2' => 'IP address is missing',
                                 '3' => 'Email address or subscriber ID must be provided',
                                 '4' => 'Invalid subscriber list ID',
                                 '5' => 'Invalid user information',
                                 '6' => 'Invalid email address format',
                                 '7' => 'Invalid subscriber ID or email address. Subscriber information not found in the database',
                                 '8' => 'Invalid campaign ID',
                                 '9' => 'Email address already unsubscribed',
                                 '10' => 'Invalid email ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Update subscriber information in the target list
     *
     * @param   array  $options = array(
     *                                 'SubscriberID'  => Target subscriber ID  {integer} (required)
     *                                 'SubscriberListID' => Owner subscription list ID  {integer} (required)
     *                                 'EmailAddress' = > Email address  {string} (required)
     *                                 'Fields' => Custom field IDs with prefix of 'CustomField'. Ex: Fields[CustomField28] {array} (required)
     *                                 'Access' => User (or subscriber) authentication  {subscriber | admin} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorCustomFieldID ] => If there's an error with one of the provided custom fields, custom field ID number will be provided
     *                               [ErrorCustomFieldTitle ] => If there's an error with one of the provided custom fields, custom field title will be provided
     *                               [ErrorCustomFieldDescription ] => If there's an error with one of the provided custom fields, description of the error message is provided
     *                           )
     *
     */
    public function subscriberUpdate($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'EmailAddress',
                           'Fields',
                           'Access');

        //Optinal Parameters
        $optl_param = array('SubscriberID',
                           );

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscriber.Update',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing subscriber ID',
                                 '2' => 'Missing subscriber list ID',
                                 '3' => 'Missing email address',
                                 '4' => 'Invalid email address',
                                 '5' => 'Invalid subscriber list ID',
                                 '6' => 'Invalid subscriber ID',
                                 '7' => 'Subscriber already exists',
                                 '8' => 'One of the provided custom fields is empty. Custom field ID and title is provided as an additional output parameter',
                                 '9' => 'One of the provided custom field value already exists in the database. Please enter a different value. Custom field ID and title is provided as an additional output parameter',
                                 '10' => 'One of the provided custom field value failed in validation checking. Custom field ID and title is provided as an additional output parameter',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Update many subscribers information at once in the target list
     *
     * @param   array  $options = array(
     *                                 'SubscriberIDs'  => Target subscriber IDs  {array}{integer} (required)
     *                                 'SubscriberListID' => Owner subscription list ID  {integer} (required)
     *                                 'EmailAddresses' = > Target subscriber Email address  {array}{string} (required)
     *                                 'Fields' => Custom field IDs with prefix of 'CustomField'. Ex: Fields[CustomField28] {array} (required)
     *                                 'Access' => User (or subscriber) authentication  {subscriber | admin} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorCustomFieldID ] => If there's an error with one of the provided custom fields, custom field ID number will be provided
     *                               [ErrorCustomFieldTitle ] => If there's an error with one of the provided custom fields, custom field title will be provided
     *                               [ErrorCustomFieldDescription ] => If there's an error with one of the provided custom fields, description of the error message is provided
     *                           )
     *
     */
    public function subscribersUpdate($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'Fields',
                           'Access');

        //Optinal Parameters
        $optl_param = array('SubscriberIDs',
                            'EmailAddresses');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscribers.Update',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing subscriber list ID',
                                 '2' => 'Either SubscriberIDs OR EmailAddresses is required',
                                 '3' => 'Subscriber list not found',
                                 '4' => 'Not all records updated',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);
        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Deletes subscriber accounts
     *
     * @param   array  $options = array(
     *                                 'SubscriberListID'  => ID number of the target subscriber list {integer} (required)
     *                                 'Subscribers' => ID number of subscribers separated by comma (Ex: 1,3,10) {string} (required)
     *                                 'Suppressed' => Remove subscribers from the supression list {boolean}
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     *
     */
    public function subscribersDelete($options = array()){

        //Required Parameters
        $req_param = array('SubscriberListID',
                           'Subscribers');

        //Optinal Parameters
        $optl_param = array('Suppressed');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Subscribers.Delete',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing subscriber ID number(s)',
                                 '2' => 'Missing subscriber list ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                  Settings Operation                      *
     *************************************************************/

    //TO - DO
    public function settingsUpdate(){}

    /**
     * Tests provided email sending settings
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [EmailSettingsErrorMessage ] => If an error occurs on email settings, error message is provided
     *                           )
     *
     */
    public function settingsEmailSendingTest(){

       //Post Data
        $post_params = array('SessionID'                  => $this->getSessionID(),
                             'Command'                    => 'Settings.EmailSendingTest',
                             'ResponseFormat'             => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Email settings failed. Check for returned error message',
                                 '2' => 'Invalid field value',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /************************************************************
     *                    Tag Operation                         *
     *************************************************************/

    /**
     * Create a tag to be used in campaign filtering
     *
     * @param   array  $options = array(
     *                                 'Tag'  => Name of the tag which is going to be created  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TagID  ] => ID number of the tag
     *                           )
     */
    public function tagCreate($options = array()){

        //Required Parameters
        $req_param = array('Tag');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Tag.Create',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Tag name is missing',
                                 '2' => 'Theres an already tag with the same name',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Returns the list of tags in the user account
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [TotalTagCount ] => Number of tag records returned
     *                               [Tags ] => List of tags
     *                           )
     */
    public function tagsGet(){

       //Post Data
        $post_params = array('SessionID'                  => $this->getSessionID(),
                             'Command'                    => 'Tags.Get',
                             'ResponseFormat'             => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Update an existing tag
     *
     * @param   array  $options = array(
     *                                 'TagID'  => ID number of the target tag {integer} (required)
     *                                 'Tag' => New name of the tag {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function tagUpdate($options = array()){

        //Required Parameters
        $req_param = array('TagID',
                           'Tag');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Tag.Update',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Delete tags
     *
     * @param   array  $options = array(
     *                                 'Tags'  => Tag ID numbers separated with comma  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function tagsDelete($options = array()){

        //Required Parameters
        $req_param = array('Tags');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Tags.Delete',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Assigns tag to target campaigns
     *
     * @param   array  $options = array(
     *                                 'TagID'  => ID number of the tag which is going to be assigned {integer} (required)
     *                                 'CampaignIDs' = > ID number of campaigns for the tag assignment {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function tagAssignToCampaigns($options = array()){

        //Required Parameters
        $req_param = array('TagID',
                           'CampaignIDs');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Tag.AssignToCampaigns',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Unassign tag from campaigns
     *
     * @param   array  $options = array(
     *                                 'TagID'  => ID number of the tag which is going to be unassigned from campaigns  {integer} (required)
     *                                 'CampaignIDs' = > ID number of campaigns for the tag removal  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function tagUnassignFromCampaigns($options = array()){

        //Required Parameters
        $req_param = array('TagID',
                           'CampaignIDs');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Tag.UnassignFromCampaigns',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /************************************************************
     *                    Users Operation                       *
     *************************************************************/

    /**
     * Updates the user email credits
     *
     * @param   array  $options = array(
     *                                 'UserID'  => Target user ID to update  {integer} (required)
     *                                 'Credits' = > Number of credits you wish to add to user account  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function userAddCredits($options = array()){

        //Required Parameters
        $req_param = array('UserID',
                           'Credits');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'User.AddCredits',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'User ID is missing',
                                 '2' => 'Credits is missing',
                                 '3' => 'User information not found',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Returns the user information
     *
     * @param   array  $options = array(
     *                                 'UserID'  => ID number of the target user account (or email address)  {integer} (required)
     *                                 'EmailAddress' = > Email address of the target user account (or user ID) {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [UserInformation] => User information
     *                           )
     */
    public function userGet($options = array()){

        //Required Parameters
        $req_param = array('UserID',
                           'EmailAddress');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'User.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'User ID is missing',
                                 '2' => 'EmailAddress is missing',
                                 '3' => 'No user account found',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Update the user information
     *
     * @param   array  $options = array(
     *                                 'UserID '  => Target user ID to update  {integer} (required)
     *                                 'RelUserGroupID ' = > ID number of the user group which is assigned to the user {integer} (required)
     *                                 'EmailAddress ' => Email address of the user  {string} (required)
     *                                 'Username ' => Username of the user  {string} (required)
     *                                 'Password ' => Password of the user {string} (required)
     *                                 'ReputationLevel ' => Reputation level of the user {Trusted | Untrusted} (required)
     *                                 'UserSince ' => Sign-up date of the user  {YYYY-MM-DD HH: MM: SS} (required)
     *                                 'FirstName ' => First name of the user  {string} (required)
     *                                 'LastName ' => Last name of the user  {string} (required)
     *                                 'Companyname ' => Company name of the user  {string} (required)
     *                                 'Website ' => Website of the user  {string} (required)
     *                                 'Street ' => Street of the user  {string} (required)
     *                                 'City ' => City of the user {string} (required)
     *                                 'State ' => State of the user {string} (required)
     *                                 'Zip ' => Zip of the user {string} (required)
     *                                 'Country ' => Country of the user  {string} (required)
     *                                 'Phone ' => Phone of the user {string} (required)
     *                                 'Fax' => Fax of the user {string} (required)
     *                                 'TimeZone' => TimeZone of the user  {string} (required)
     *                                 'SignUpIPAddress' => IP address of the user during sign-up process  {string} (required)
     *                                 'APIKey' => API key of the user {string} (required)
     *                                 'Language' => Language of the user  {string} (required)
     *                                 'LastActivityDateTime' => Last activity time of the user  {YYYY-MM-DD HH: MM: SS} (required)
     *                                 'PreviewMyEmailAccount' => If user is subscribed to PreviewMyEmail.com service, subdomain of the account  {string} (required)
     *                                 'PreviewMyEmailAPIKey ' => If user is subscribed to PreviewMyEmail.com service, API key of the account {string} (required)
     *                                 'AccountStatus ' => Set to "Disabled" if you wish to disable user account, else set it to "Enabled"  {Enabled | Disabled} (required)
     *                                 'AvailableCredits ' => Set the available credits for the user  {Integer} (>=v4.1.0) (optional)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function userUpdate($options = array()){

        //Required Parameters
        $req_param = array('UserID',
                           'RelUserGroupID',
                           'EmailAddress',
                           'Username',
                           'Password',
                           'ReputationLevel',
                           'UserSince',
                           'FirstName',
                           'LastName',
                           'Companyname',
                           'Website',
                           'Street',
                           'City',
                           'State',
                           'Zip',
                           'Country',
                           'Phone',
                           'Fax',
                           'TimeZone',
                           'SignUpIPAddress',
                           'APIKey',
                           'Language',
                           'LastActivityDateTime',
                           'PreviewMyEmailAccount',
                           'PreviewMyEmailAPIKey',
                           'AccountStatus');

        //Optinal Parameters
        $optl_param = array('AvailableCredits');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'User.Update',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'User ID is missing',
                                 '2' => 'Authentication failed',
                                 '3' => 'PreviewMyEmail.com connection error occurred',
                                 '4' => 'Invalid PreviewMyEmail.com access information',
                                 '5' => 'Invalid user ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Verifies the provided username and password then logs the user in.
     *
     * @param   array  $options = array(
     *                                 'Username'  => Username of the user to be logged in  {string} (required)
     *                                 'Password ' = > Password of the user to be logged in  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [SessionID ] => SessionID of the logged in user
     *                           )
     */
    public function userLogin($options = array()){

        //Required Parameters
        $req_param = array('Username',
                           'Password');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'User.Login',
                                         'ResponseFormat'             => 'JSON',
                                         'RememberMe'                 => null,
                                         'Captcha'                    => null,
                                         'DisableCaptcha'             => true), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Username is missing',
                                 '2' => 'Password is missing',
                                 '3' => 'Invalid login information',
                                 '4' => 'Invalid image verification',
                                 '5' => 'Image verification failed',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //if Succes - Setting SessionID
        if($result && $result->Success == 1){
            //Setting Session ID
            $this->setSessionID($result->SessionID);
        }

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Update the user information
     *
     * @param   array  $options = array(
     *                                 'RelUserGroupID ' = > ID number of the user group which is assigned to the user {integer} (required)
     *                                 'EmailAddress ' => Email address of the user  {string} (required)
     *                                 'Username ' => Username of the user  {string} (required)
     *                                 'Password ' => Password of the user {string} (required)
     *                                 'ReputationLevel ' => Reputation level of the user {Trusted | Untrusted} (required)
     *                                 'FirstName ' => First name of the user  {string} (required)
     *                                 'LastName ' => Last name of the user  {string} (required)
     *                                 'Companyname ' => Company name of the user  {string} (required)
     *                                 'Website ' => Website of the user  {string} (required)
     *                                 'Street ' => Street of the user  {string} (required)
     *                                 'City ' => City of the user {string} (required)
     *                                 'State ' => State of the user {string} (required)
     *                                 'Zip ' => Zip of the user {string} (required)
     *                                 'Country ' => Country of the user  {string} (required)
     *                                 'Phone ' => Phone of the user {string} (required)
     *                                 'Fax' => Fax of the user {string} (required)
     *                                 'TimeZone' => TimeZone of the user  {string} (required)
     *                                 'Language' => Language of the user  {string} (required)
     *                                 'PreviewMyEmailAccount' => If user is subscribed to PreviewMyEmail.com service, subdomain of the account  {string} (required)
     *                                 'PreviewMyEmailAPIKey ' => If user is subscribed to PreviewMyEmail.com service, API key of the account {string} (required)
     *                                 'AvailableCredits ' => Set the available credits for the user  {Integer} (>=v4.1.0) (optional)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [UserID ] => ID number of the new user account
     *                           )
     */
    public function userCreate($options = array()){

        //Required Parameters
        $req_param = array('RelUserGroupID',
                           'EmailAddress',
                           'Username',
                           'Password',
                           'FirstName',
                           'LastName',
                           'TimeZone',
                           'Language',
                           'ReputationLevel',
                           'CompanyName',
                           'Website',
                           'Street',
                           'City',
                           'State',
                           'Zip',
                           'Country',
                           'Phone',
                           'Fax',
                           'PreviewMyEmailAccount',
                           'PreviewMyEmailAPIKey');

        //Optinal Parameters
        $optl_param = array('AvailableCredits');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'User.Create',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing user group ID',
                                 '2' => 'Missing email address',
                                 '3' => 'Username is missing',
                                 '4' => 'Password is missing',
                                 '5' => 'Reputation level is missing',
                                 '6' => 'Missing first name',
                                 '7' => 'Missing last name',
                                 '8' => 'Missing time zone',
                                 '9' => 'Missing language',
                                 '10' => 'Invalid email address',
                                 '11' => 'Invalid user group ID',
                                 '12' => 'Username is already registered',
                                 '13' => 'Email address is already registered',
                                 '14' => 'Invalid language. Not supported',
                                 '15' => 'Invalid reputation level',
                                 '16' => 'Maximum allowed user accounts in your license exceeded',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Sends the password reset email.
     *
     * @param   array  $options = array(
     *                                 'EmailAddress  ' = > Email address of the user to be reminded {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function userPasswordRemind($options = array()){

        //Required Parameters
        $req_param = array('EmailAddress');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('Command'                    => 'User.PasswordRemind',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Email address is missing',
                                 '2' => 'Invalid email address',
                                 '3' => 'Email address not found in user database',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Resets user's password and sends the new password with email.
     *
    * @param   array  $options = array(
     *                                 'UserID ' = > ID of the user whose password will be reset (encrypted with md5)  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function userPasswordReset($options = array()){

        //Required Parameters
        $req_param = array('UserID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('Command'                    => 'User.PasswordReset',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'User id is missing',
                                 '2' => 'Invalid user id',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Switches to the requested user and administrator will be able to login to that user with full or default privileges
     *
    * @param   array  $options = array(
     *                                 'UserID  ' = > User to switch {integer} (required)
     *                                 'PrivilegeType' => How to navigate in user area {Default | Full} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText] => Error Text
     *                           )
     */
    public function userSwitch($options = array()){

        //Required Parameters
        $req_param = array('UserID',
                           'PrivilegeType');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'User.Switch',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'User id is missing',
                                 '2' => 'Invalid user id',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /**
     * Returns the list of payments periods for a user (includes totals)
     *
     * @param   array  $options = array(
     *                                 'UserID ' = > User to switch  {integer} (required)
     *                                 'PaymentStatus' => Enter one of the options to filter the list  {NA | Unpaid | Waiting | Paid | Waived} (required)
     *                                 'ReturnFormatted ' => Returns numbers formatted or not  {Yes | No} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText] => Error Text
     *
     *                               //On Success
     *                               [PaymentPeriods ] => List of payment periods
     *                           )
     */
    public function userPaymentPeriods($options = array()){

        //Required Parameters
        $req_param = array('UserID',
                           'PaymentStatus',
                           'ReturnFormatted');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'User.PaymentPeriods',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'User id is missing',
                                 '2' => 'Invalid user information',
                                 '3' => 'Invalid payment status value',
                                 '99997' => 'This is not an ESP Oempro license',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Updates the payments period of a user
     *
     * @param   array  $options = array(
     *                                 'UserID ' = > User to switch  {integer} (required)
     *                                 'LogID ' => Payment period to update  {integer} (required)
     *                                 'Discount  ' => Discount amount {double} (required)
     *                                 'IncludeTax ' => To apply discount or not  {Include | Exclude} (required)
     *                                 'PaymentStatus' => Payment status of the period  {NA | Unpaid | Waiting | Paid | Waived} (required)
     *                                 'ReturnFormatted ' => Returns numbers formatted or not  {Yes | No} (required)
     *                                 'SendReceipt ' => Sends receipt email to the user if set to 'Yes' {Yes | No} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText] => Error Text
     *
     *                               //On Success
     *                               [PaymentPeriods ] => List of payment periods
     *                           )
     */
    public function userPaymentPeriodsUpdate($options = array()){

        //Required Parameters
        $req_param = array('UserID',
                           'LogID',
                           'Discount',
                           'IncludeTax',
                           'PaymentStatus',
                           'ReturnFormatted',
                           'SendReceipt');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'User.PaymentPeriods.Update',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'User id is missing',
                                 '2' => 'Invalid user information',
                                 '3' => 'Log ID is missing',
                                 '4' => 'Invalid payment period (log ID)',
                                 '5' => 'Invalid include tax paramete',
                                 '6' => 'Invalid payment status',
                                 '99997' => 'This is not an ESP Oempro license',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Deletes given users.
     *
     * @param   array  $options = array(
     *                                 'Users  ' = > Comma delimeted User ids  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText] => Error Text
     *                           )
     */
    public function usersDelete($options = array()){

        //Required Parameters
        $req_param = array('Users');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Users.Delete',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'User ids is missing',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves users
     *
     * @param   array  $options = array(
     *                                 'OrderField ' = > Name of the field to order based on. For multiple ordering, separate fields with pipe '|'  {string} (required)
     *                                 'OrderType ' => Ascending or descending ordering. For multiple ordering, separate fields with pipe '|' {ASC | DESC} (required)
     *                                 'RelUserGroupID ' => User group ID of users (integer) or account status ("Enabled", "Disabled") or online status ('Online')  {mixed (required)
     *                                 'RecordsPerRequest ' => How many rows to return per page  {integer} (required)
     *                                 'RecordsFrom ' => Start from (starts from zero)  {integer} (required)
     *                                 'SearchField ' => Name of the field  {string} (optional)
     *                                 'SearchKeyword' => Keyword for searching {string} (optional)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [ErrorText] => Error Text
     *
     *                               //On Success
     *                               [Users ] => Returns the list of all users in arra
     *                               [TotalUsers ] => Returns the total number of users
     *                           )
     *
     */
    public function usersGet($options = array()){

        //Required Parameters
        $req_param = array('OrderField',
                           'OrderType',
                           'RelUserGroupID',
                           'RecordsPerRequest',
                           'RecordsFrom');

        //Optinal Parameters
        $optl_param = array('SearchField',
                            'SearchKeyword');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Users.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /************************************************************
     *            User Interface Themes Operation               *
     *************************************************************/


    /**
     * Creates a new theme
     *
     * @param   array  $options = array(
     *                                 'Template ' = > Template code {string} (required)
     *                                 'ThemeName  ' => Name of the new theme {string} (required)
     *                                 'ProductName  ' => Branding the product, name of the product  {string} (required)
     *                                 'ThemeSettings  ' => CSS theme settings.  {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [ThemeID ] => Theme ID of the new theme
     *                           )
     */
    public function themeCreate($options = array()){

        //Required Parameters
        $req_param = array('Template',
                           'ThemeName',
                           'ProductName',
                           'ThemeSettings');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Theme.Create',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing template code',
                                 '2' => 'Missing theme name',
                                 '3' => 'Invalid template code',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Gets the details of a theme
     *
     * @param   array  $options = array(
     *                                 'ThemeID  ' = > Theme ID to get  {integer} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [Theme  ] => Theme data
     *                           )
     */
    public function themeGet($options = array()){

        //Required Parameters
        $req_param = array('ThemeID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Theme.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing theme ID',
                                 '2' => 'Invalid theme ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    //TO - DO
    public function themeUpdate($ThemeID = null){}


    /**
     * Deletes a theme
     *
     * @param   array  $options = array(
     *                                 'Themes ' = > ID numbers of themes separated by comma {string} (required)
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function themeDelete($options = array()){

        //Required Parameters
        $req_param = array('Themes');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'Theme.Delete',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'At least one theme ID must be provided',
                                 '2' => 'This is the only theme in the system. It can not be deleted',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Returns the list of themes
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [Themes ] => List of available themes
     *                           )
     */
    public function themesGet(){

       //Post Data
        $post_params = array('SessionID'                  => $this->getSessionID(),
                             'Command'                    => 'Themes.Get',
                             'ResponseFormat'             => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }

    /************************************************************
     *                      User Groups Operation               *
     *************************************************************/

    /**
     * Creates a new user group
     *
     * @param   array  $options = array(
     *                                GroupName '=> {string} (required) Name of the group
     *                                RelThemeID '=> {integer} (required) Theme ID for the group
     *                                SubscriberAreaLogoutURL '=> {string} (required) URL where subscriber will be redirected after logout
     *                                'ForceUnsubscriptionLink '=> {Enabled | Disabled} (required) Whether force user to include unsubscription link inside emails or not
     *                                'ForceRejectOptLink '=> {Enabled | Disabled} (required) Whether force user to include reject link inside opt-in confirmation emails
     *                                'Permissions '=> {string} (required) Permission list separated by comma
     *                                'PaymentSystem '=> {Enabled | Disabled} (required) Payment system status
     *                                'PaymentPricingRange '=> {string} (required) Payment pricing for per recipient and per auto responder pricing models. Data must be provided in following format: until|price Example: 1000|0.00,5000|0.01,-1|0.07
     *                                'PaymentCampaignsPerRecipient '=> {Enabled | Disabled} (required) Whether to charge per campaign recipient or not
     *                                'PaymentCampaignsPerCampaignCost '=> {double} (required) Pricing per sent campaign
     *                                'PaymentAutoRespondersChargeAmount '=> {double} (required) Monthly auto responder pricing fee
     *                                'PaymentAutoRespondersPerRecipient '=> {Enabled | Disabled} (required) Whether to charge per sent auto responder or not
     *                                'PaymentDesignPrevChargeAmount '=> {double} (required) Monthly design preview pricing fee
     *                                'PaymentDesignPrevChargePerReq '=> {double} (required) Per design preview request fee
     *                                'PaymentSystemChargeAmount '=> {double} (required) Monthly system usage/subscription fee
     *                                'LimitSubscribers '=> {integer} (required) Maximum number of subscribers that user can store in the account
     *                                'LimitLists '=> {integer} (required) Maximum number of lists that user can store in the account
     *                                'LimitCampaignSendPerPeriod '=> {integer} (required) Maximum number of campaigns that user can send every month
     *                                'LimitEmailSendPerPeriod '=> {integer} (required) Maximum number of emails that user can send every month
     *                                'ThresholdImport '=> {integer} (required, 4.0.5+) The threshold value to trigger admin notification systems during the import
     *                                'ThresholdEmailSend '=> {integer} (required, 4.0.5+) The threshold value to trigger admin notification systems during the email delivery
     *                                'TrialGroup '=> {Enabled | Disabled} (required, 4.1.0+) Set the user group as trial or not
     *                                'SendMethod '=> {System | SMTP | LocalMTA | PHPMail | PowerMTA | SaveToDisk} (required, v4.1.0+) Set a different sending method for the user group
     *                                'TrialExpireSeconds '=> {integer} (optional, 4.1.0+) Number of seconds after the user sign-up to expire
     *                                'PlainEmailHeader '=> {string} (optional, 4.1.0+) The header content for text only email campaigns
     *                                'PlainEmailFooter '=> {string} (optional, 4.1.0+) The footer content for text only email campaigns
     *                                'HTMLEmailHeader '=> {string} (optional, 4.1.0+) The header content for HTML email campaigns
     *                                'HTMLEmailFooter '=> {string} (optional, 4.1.0+) The footer content for HTML email campaigns
     *                                'SendMethodSaveToDiskDir '=> {string} (optional, 4.1.0+) Path to save emails. It should be writable
     *                                'SendMethodPowerMTAVMTA '=> {string} (optional, 4.1.0+) Virtual MTA name to send emails from through PowerMTA
     *                                'SendMethodPowerMTADir '=> {string} (optional, 4.1.0+) PowerMTA pick-up directory. It should be writable
     *                                'SendMethodLocalMTAPath '=> {string} (optional, 4.1.0+) Path of your local MTA. It should be executable via PHP and web server user
     *                                'SendMethodSMTPHost '=> {string} (optional, 4.1.0+) Host or IP address of the SMTP server
     *                                'SendMethodSMTPPort '=> {integer} (optional, 4.1.0+) Port number (usually 25) of the SMTP server
     *                                'SendMethodSMTPSecure '=> { ssl | tls | } (optional, 4.1.0+) SMTP server security mode
     *                                'SendMethodSMTPTimeOut '=> {integer} (optional, 4.1.0+) Set the number of seconds to time-out during inactivity
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                                //On Success
     *                               [UserGroupID  ] => ID number of the new user group
     *                           )
     */
    public function userGroupCreate($options = array()){

        //Required Parameters
        $req_param = array('GroupName',
                           'RelThemeID',
                           'SubscriberAreaLogoutURL',
                           'ForceUnsubscriptionLink',
                           'ForceRejectOptLink',
                           'Permissions',
                           'PaymentSystem',
                           'PaymentPricingRange',
                           'PaymentCampaignsPerRecipient',
                           'PaymentCampaignsPerCampaignCost',
                           'PaymentAutoRespondersChargeAmount',
                           'PaymentAutoRespondersPerRecipient',
                           'PaymentDesignPrevChargeAmount',
                           'PaymentDesignPrevChargePerReq',
                           'PaymentSystemChargeAmount',
                           'LimitSubscribers',
                           'LimitLists',
                           'LimitCampaignSendPerPeriod',
                           'LimitEmailSendPerPeriod',
                           'ThresholdImport',
                           'ThresholdEmailSend',
                           'TrialGroup',
                           'SendMethod');

        //Optinal Parameters
        $optl_param = array('TrialExpireSeconds',
                            'PlainEmailHeader',
                            'PlainEmailFooter',
                            'HTMLEmailHeader',
                            'HTMLEmailFooter',
                            'SendMethodSaveToDiskDir',
                            'SendMethodPowerMTAVMTA',
                            'SendMethodPowerMTADir',
                            'SendMethodLocalMTAPath',
                            'SendMethodSMTPHost',
                            'SendMethodSMTPPort',
                            'SendMethodSMTPSecure',
                            'SendMethodSMTPTimeOut',
                            'SendMethodSMTPAuth',
                            'SendMethodSMTPUsername',
                            'SendMethodSMTPPassword');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                         => $this->getSessionID(),
                                         'Command'                           => 'UserGroup.Create',
                                         'ResponseFormat'                    => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing user group name',
                                 '2' => 'Missing subscriber area logout url',
                                 '3' => 'Missing user permissions',
                                 '4' => 'Missing payment system status',
                                 '5' => 'Missing subscriber limit',
                                 '6' => 'Missing list limit',
                                 '7' => 'Missing campaigns per period limit',
                                 '8' => 'Missing theme ID',
                                 '9' => 'Campaign per recipient pricing status is missing',
                                 '10' => 'Per campaign pricing status is missing',
                                 '11' => 'Auto responder periodical charge amount is missing',
                                 '12' => 'Per auto responder send pricing is missing',
                                 '13' => 'Design preview periodical charge amount is missing',
                                 '14' => 'Per design preview pricing is missing',
                                 '15' => 'Periodical system subscription pricing is missing',
                                 '16' => 'Pricing range is missing',
                                 '17' => 'force unsubscription link is missing',
                                 '18' => 'force opt-in confirm link is missing',
                                 '19' => 'Invalid theme ID',
                                 '20' => 'Missing email delivery per month limitation',
                                 '21' => 'The trial period is missing',
                                 '22' => 'Invalid send method',
                                 '23' => 'Invalid send method security type',
                                 '24' => 'Invalid SMTP auth type',
                                 '25' => 'Invalid send method. Testing failed.',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Updates user group
     *
     * @param   array  $options = array(
     *                                  'UserGroupID '=> {integer} (required) ID number of the target user group
     *                                  'GroupName '=> {string} (required) Name of the group
     *                                  'RelThemeID '=> {integer} (required) Theme ID for the group
     *                                  'SubscriberAreaLogoutURL '=> {string} (required) URL where subscriber will be redirected after logout
     *                                  'ForceUnsubscriptionLink '=> {Enabled | Disabled} (required) Whether force user to include unsubscription link inside emails or not
     *                                  'ForceRejectOptLink '=> {Enabled | Disabled} (required) Whether force user to include reject link inside opt-in confirmation emails
     *                                  'Permissions '=> {string} (required) Permission list separated by comma
     *                                  'PaymentSystem '=> {Enabled | Disabled} (required) Payment system status
     *                                  'PaymentPricingRange '=> {string} (required) Payment pricing for per recipient and per auto responder pricing models. Data must be provided in following format: until|price Example: 1000|0.00,5000|0.01,-1|0.07
     *                                  'PaymentCampaignsPerRecipient '=> {Enabled | Disabled} (required) Whether to charge per campaign recipient or not
     *                                  'PaymentCampaignsPerCampaignCost '=> {double} (required) Pricing per sent campaign
     *                                  'PaymentAutoRespondersChargeAmount '=> {double} (required) Monthly auto responder pricing fee
     *                                  'PaymentAutoRespondersPerRecipient '=> {Enabled | Disabled} (required) Whether to charge per sent auto responder or not
     *                                  'PaymentDesignPrevChargeAmount '=> {double} (required) Monthly design preview pricing fee
     *                                  'PaymentDesignPrevChargePerReq '=> {double} (required) Per design preview request fee
     *                                  'PaymentSystemChargeAmount '=> {double} (required) Monthly system usage/subscription fee
     *                                  'LimitSubscribers '=> {integer} (required) Maximum number of subscribers that user can store in the account
     *                                  'LimitLists '=> {integer} (required) Maximum number of lists that user can store in the account
     *                                  'LimitCampaignSendPerPeriod '=> {integer} (required) Maximum number of campaigns that user can send every month
     *                                  'LimitEmailSendPerPeriod '=> {integer} (required) Maximum number of emails that user can send every month
     *                                  'TrialGroup '=> {Enabled | Disabled} (required, 4.1.0+) Set the user group as trial or not
     *                                  'SendMethod '=> {System | SMTP | LocalMTA | PHPMail | PowerMTA | SaveToDisk} (required, v4.1.0+) Set a different sending method for the user group
     *                                  'TrialExpireSeconds '=> {integer} (optional, 4.1.0+) Number of seconds after the user sign-up to expire
     *                                  'PlainEmailHeader '=> {string} (optional, 4.1.0+) The header content for text only email campaigns
     *                                  'PlainEmailFooter '=> {string} (optional, 4.1.0+) The footer content for text only email campaigns
     *                                  'HTMLEmailHeader '=> {string} (optional, 4.1.0+) The header content for HTML email campaigns
     *                                  'HTMLEmailFooter '=> {string} (optional, 4.1.0+) The footer content for HTML email campaigns
     *                                  'SendMethodSaveToDiskDir '=> {string} (optional, 4.1.0+) Path to save emails. It should be writable
     *                                  'SendMethodPowerMTAVMTA '=> {string} (optional, 4.1.0+) Virtual MTA name to send emails from through PowerMTA
     *                                  'SendMethodPowerMTADir '=> {string} (optional, 4.1.0+) PowerMTA pick-up directory. It should be writable
     *                                  'SendMethodLocalMTAPath '=> {string} (optional, 4.1.0+) Path of your local MTA. It should be executable via PHP and web server user
     *                                  'SendMethodSMTPHost '=> {string} (optional, 4.1.0+) Host or IP address of the SMTP server
     *                                  'SendMethodSMTPPort '=> {integer} (optional, 4.1.0+) Port number (usually 25) of the SMTP server
     *                                  'SendMethodSMTPSecure '=> { ssl | tls | } (optional, 4.1.0+) SMTP server security mode
     *                                  'SendMethodSMTPTimeOut '=> {integer} (optional, 4.1.0+) Set the number of seconds to time-out during inactivity
     *                                  'SendMethodSMTPAuth '=> {true | false} (optional, 4.1.0+) Set whether SMTP server requires authentication or not
     *                                  'SendMethodSMTPUsername '=> {string} (optional, 4.1.0+) The username to login to the SMTP server
     *                                  'SendMethodSMTPPassword '=> {string} (optional, 4.1.0+) The password to login to the SMTP server
     *                                 )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
   public function userGroupUpdate($options = array()){


        //Required Parameters
        $req_param = array('UserGroupID',
                           'GroupName',
                           'RelThemeID',
                           'SubscriberAreaLogoutURL',
                           'ForceUnsubscriptionLink',
                           'ForceRejectOptLink',
                           'Permissions',
                           'PaymentSystem',
                           'PaymentPricingRange',
                           'PaymentCampaignsPerRecipient',
                           'PaymentCampaignsPerCampaignCost',
                           'PaymentAutoRespondersChargeAmount',
                           'PaymentAutoRespondersPerRecipient',
                           'PaymentDesignPrevChargeAmount',
                           'PaymentDesignPrevChargePerReq',
                           'PaymentSystemChargeAmount',
                           'LimitSubscribers',
                           'LimitLists',
                           'LimitCampaignSendPerPeriod',
                           'LimitEmailSendPerPeriod',
                           'TrialGroup',
                           'SendMethod');

        //Optinal Parameters
        $optl_param = array('TrialExpireSeconds',
                            'PlainEmailHeader',
                            'PlainEmailFooter',
                            'HTMLEmailHeader',
                            'HTMLEmailFooter',
                            'SendMethodSaveToDiskDir',
                            'SendMethodPowerMTAVMTA',
                            'SendMethodPowerMTADir',
                            'SendMethodLocalMTAPath',
                            'SendMethodSMTPHost',
                            'SendMethodSMTPPort',
                            'SendMethodSMTPSecure',
                            'SendMethodSMTPTimeOut',
                            'SendMethodSMTPAuth',
                            'SendMethodSMTPUsername',
                            'SendMethodSMTPPassword');

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }


       //Post Data
        $post_params = array_merge(array('SessionID'                         => $this->getSessionID(),
                                         'Command'                           => 'UserGroup.Update',
                                         'ResponseFormat'                    => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing user group name',
                                 '2' => 'Missing subscriber area logout url',
                                 '3' => 'Missing user permissions',
                                 '4' => 'Missing payment system status',
                                 '5' => 'Missing subscriber limit',
                                 '6' => 'Missing list limit',
                                 '7' => 'Missing campaigns per period limit',
                                 '8' => 'Missing theme ID',
                                 '9' => 'Campaign per recipient pricing status is missing',
                                 '10' => 'Per campaign pricing status is missing',
                                 '11' => 'Auto responder periodical charge amount is missing',
                                 '12' => 'Per auto responder send pricing is missing',
                                 '13' => 'Design preview periodical charge amount is missing',
                                 '14' => 'Per design preview pricing is missing',
                                 '15' => 'Periodical system subscription pricing is missing',
                                 '16' => 'Pricing range is missing',
                                 '17' => 'force unsubscription link is missing',
                                 '18' => 'force opt-in confirm link is missing',
                                 '19' => 'Invalid theme ID',
                                 '20' => 'Missing user group ID',
                                 '21' => 'Invalid user group ID',
                                 '22' => 'Missing email delivery per month limitation',
                                 '23' => 'The trial period is missing',
                                 '24' => 'Invalid send method',
                                 '25' => 'Invalid send method security type',
                                 '26' => 'Invalid SMTP auth type',
                                 '27' => 'Invalid send method. Testing failed.',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges',);

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves user group
     *
     * @param   array  $options = array(
     *                                  'UserGroupID ' => {integer} (required) ID number of the target user group
     *                                  )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [UserGroup ] => Requested user group information
     *                           )
     */
    public function userGroupGet($options = array()){

        //Required Parameters
        $req_param = array('UserGroupID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

        //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'UserGroup.Get',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing user group ID',
                                 '2' => 'Invalid user group ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Duplicates user group
     *
     * @param   array  $options = array(
     *                                  'UserGroupID ' => {string} (required) ID numbers separated with comma
     *                                  )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *
     *                               //On Success
     *                               [UserGroupID ] => ID number of the new user group
     *                           )
     */
    public function userGroupDuplicate($options = array()){

        //Required Parameters
        $req_param = array('UserGroupID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'UserGroup.Duplicate',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing user group ID',
                                 '2' => 'Invalid user group ID',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Deletes user groups
     *
     * @param   array  $options = array(
     *                                  'UserGroupID ' => {string} (required) ID numbers separated with comma
     *                                  )
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                           )
     */
    public function userGroupDelete($options = array()){

        //Required Parameters
        $req_param = array('UserGroupID');

        //Optinal Parameters
        $optl_param = array();

        //Checking if user param matches req parameters and its not empty or null
        $check_result = $this->_check_req_opt_param($req_param , $optl_param, $options);
        if($check_result->Success == 0){
            return $check_result;
        } else {
            $options = $check_result->new_options;
        }

       //Post Data
        $post_params = array_merge(array('SessionID'                  => $this->getSessionID(),
                                         'Command'                    => 'UserGroup.Delete',
                                         'ResponseFormat'             => 'JSON'), $options);

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '1' => 'Missing user group ID',
                                 '2' => 'Invalid user group ID',
                                 '3' => 'This user group is set as default group for new users. Can not be deleted',
                                 '4' => 'This is the last user group, it can not be deleted',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


    /**
     * Retrieves the list of user groups
     *
     * @return \stdClass object  $result
     *
     *                           On Success / Failure
     *                           --------------------
     *                           \stdClass Object
     *                           (
     *                               [Success] => 1 - On Success, 0 - On Failure
     *                               [ErrorCode] => \stdClass Object
     *                                   (
     *                                       [code] => Error Code
     *                                       [description] => Error Description
     *                                   )
     *                               [UserGroups ] => List of user groups available in the system
     *                           )
     */
    public function userGroupsGet(){

       //Post Data
        $post_params = array('SessionID'                  => $this->getSessionID(),
                             'Command'                    => 'UserGroups.Get',
                             'ResponseFormat'             => 'JSON');

        //Error Code & Decsription
        $error_code_desc = array('0' => 'No Error',
                                 '99998' => 'Authentication failure or session expired',
                                 '99999' => 'Not enough privileges');

        //Make Api Call
        $result =  $this->_callAPI($this->url, $post_params, $this->debug, false);

        //Retun Parameter Formatting
        $result = $this->_error_description_format($result, $error_code_desc);

        return $result;
    }


	/**
	 * Make an API call using JSON with the provided URL and post_params
	 *
	 * @param	string		$url			URL to call
	 * @param	array		$post_params	Post values
	 * @param	boolean		$debug			If true and debugging is enabled in cake then debug messages are printed out
	 * @param	boolean		$sticky			If true the API call will always be sent to the same server using the StickySession cookie value
	 * @return	mixed		Decoded JSON returned from the server
	 */
	private function _callAPI($url, $post_params, $debug, $sticky = false) {
		$this->lastErrorResult = null;
		// Send the SessionID cookie so the load balancer sticky sessions work
		if ( $sticky && ! empty($post_params[$this->StickySession])) {
			$this->snoopy->cookies["SessionID"] = $post_params[$this->StickySession];
		}
		$this->snoopy->submit($url, $post_params);
		if($debug && Configure::read('debug')) {
			print "<b>Request URL: </b>" . $url . "<br>";
			print "<b>Request Data: </b>";
			pr($post_params);
			print "<br >";
			print "<b>Snoopy Dump: </b><br />";
			pr($this->snoopy);
		}
		if ($this->snoopy->status != 200) {
			$this->lastErrorResult = $this->snoopy->results;
			return false;
		} else {
			$r = json_decode($this->snoopy->results);
			return $r;
		}
	}

	/**
	 * Return the last error result
	 *
	 * @return string	Error result
	 */
	public function lastError() {
		return $this->lastErrorResult;
	}


}