<?

// https://github.com/Wolbolar/IPSymconTPLinkHS110

class SharpTV extends IPSModule // Sharp Aquos TV
{
	public function Create()
	{
		//Never delete this line!
		parent::Create();
        $this->RegisterPropertyString("Ip", "192.168.1.102");
        $this->RegisterPropertyString("Port", 10002);
        $this->RegisterPropertyInteger("modelselection", 1);
		$this->RegisterPropertyInteger("stateinterval", 0);
		$this->RegisterPropertyInteger("systeminfointerval", 0);
		$this->RegisterPropertyString("softwareversion", "");
		$this->RegisterPropertyFloat("hardwareversion", 0);
		$this->RegisterPropertyString("deviceid", "");
		$this->RegisterPropertyString("hardwareid", "");
		$this->RegisterPropertyString("firmwareid", "");
		$this->RegisterPropertyString("devicename", "");
		$this->RegisterTimer('StateUpdate', 0, 'SharpTV_StateTimer(' . $this->InstanceID . ');');
		$this->RegisterTimer('SystemInfoUpdate', 0, 'SharpTV_SystemInfoTimer(' . $this->InstanceID . ');');
		//we will wait until the kernel is ready
		$this->RegisterMessage(0, IPS_KERNELMESSAGE);
	}

	public function ApplyChanges()
	{
		//Never delete this line!
		parent::ApplyChanges();

		if (IPS_GetKernelRunlevel() !== KR_READY) {
			return;
		}

		$this->RegisterVariableBoolean("State", "Status", "~Switch", 1);
		$this->EnableAction("State");
		$model = $this->ReadPropertyInteger("modelselection");
		if ($model == 2) {
			$this->RegisterProfile('TPLinkHS.Milliampere', '', '', " mA", 0, 0, 0, 0, 2);

			$this->RegisterVariableFloat("Voltage", $this->Translate("Voltage"), "Volt.230", 2);
			$this->RegisterVariableFloat("Power", $this->Translate("Power"), "Watt.14490", 3);
			$this->RegisterVariableFloat("Current", $this->Translate("Electricity"), "TPLinkHS.Milliampere", 4);
			$this->RegisterVariableFloat("Work", $this->Translate("Work"), "Electricity", 5);
		}
		//$this->ValidateConfiguration(); // temp avstangd
	}

	private function ValidateConfiguration()
	{
		// Types HS100, HS105, HS110, HS200
		$host = $this->ReadPropertyString('Host');

		//IP TP Link check
		if (!filter_var($host, FILTER_VALIDATE_IP) === false) {
			//IP ok
			$ipcheck = true;
		} else {
			$ipcheck = false;
		}

		//Domain TP Link Device check
		if (!$this->is_valid_localdomain($host) === false) {
			//Domain ok
			$domaincheck = true;
		} else {
			$domaincheck = false;
		}

		if ($domaincheck === true || $ipcheck === true) {
			$hostcheck = true;
			$this->SetStatus(102);
		} else {
			$hostcheck = false;
			$this->SetStatus(203); //IP Adresse oder Host ist ungültig
		}
		//$extendedinfo = $this->ReadPropertyBoolean("extendedinfo");
		if ($extendedinfo) {
			$this->SendDebug("TP Link:", "extended info activ", 0);
		}
		$this->SetStateInterval($hostcheck);
		$this->SetSystemInfoInterval($hostcheck);
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
	{

		switch ($Message) {
			case IM_CHANGESTATUS:
				if ($Data[0] === IS_ACTIVE) {
					$this->ApplyChanges();
				}
				break;

			case IPS_KERNELMESSAGE:
				if ($Data[0] === KR_READY) {
					$this->ApplyChanges();
				}
				break;

			default:
				break;
		}
	}

	public function StateTimer()
	{
		$this->GetSystemInfo();
	}

	public function SystemInfoTimer()
	{
		$this->GetRealtimeCurrent();
	}

	public function ResetWork()
	{
		$result = SetValueFloat($this->GetIDForIdent("Work"), 0.0);
		return $result;
	}

	protected function SetStateInterval($hostcheck)
	{
		if ($hostcheck) {
			$devicetype = $this->ReadPropertyInteger("modelselection");
			$stateinterval = $this->ReadPropertyInteger("stateinterval");
			$interval = $stateinterval * 1000;
			if ($devicetype == 2) {
				$this->SetTimerInterval("StateUpdate", $interval);
			} else {
				$this->SetTimerInterval("StateUpdate", $interval);
			}
		}
	}

	protected function SetSystemInfoInterval($hostcheck)
	{
		if ($hostcheck) {
			$devicetype = $this->ReadPropertyInteger("modelselection");
			$infointerval = $this->ReadPropertyInteger("systeminfointerval");
			$interval = $infointerval * 1000;
			if ($devicetype == 2) {
				$this->SetTimerInterval("SystemInfoUpdate", $interval);
			} else {
				$this->SetTimerInterval("SystemInfoUpdate", 0);
			}
		}
	}
      
	protected function SendToSharpTV($command) // e.g function PowerOn() calls this function
	{
		$ip = $this->ReadPropertyString('Ip');
        $port = $this->ReadPropertyString('Port');
        
        //Connect to Server
        $socket = stream_socket_client("{$ip}:{$port}", $errno, $errstr, 0.3);
        
        if (!$socket) {
            IPS_LogMessage("SharpTV", "Could not connect to socket ");
         }
        
        if($socket) {
            //Send a command
            fwrite($socket, $command);
            IPS_LogMessage("SharpTV", "Message sent " . $command);
            $buf = null;
            
            //Receive response from server
            //while (!feof($socket)) { // Loop until the response is finished
            //$buf .= fread($socket, 1024); // the .= (dot) is Concatenation assignment
            $buf = fread($socket, 1024);
            
            IPS_LogMessage("SharpTV", "Message recived " . $buf);
             //}
             //close connection
             fclose($socket);
             
             $this->SendDebug("SharpTV:", "Close Socket", 0);
             return $buf;
             }
    }


	// Power On
	public function PowerOn() // steg 1
	{
        $command = 'POWR1   ';
		$result = $this->SendToSharpTV($command);
		return $result;
	}

	// Power Off
	public function PowerOff()
	{
		$command = 'POWR0   ';
		$result = $this->SendToSharpTV($command);
		return $result;
	}

	// Reset (To Factory Settings)
	public function Reset()
	{
		$command = '{"system":{"reset":{"delay":1}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Get Device Icon
	public function GetDeviceIcon()
	{
		$command = '{"system":{"get_dev_icon":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Set Device Icon
	public function SetDeviceIcon(string $icon, string $hash)
	{
		$command = '{"system":{"set_dev_icon":{"icon":"' . $icon . '","hash":"' . $hash . '"}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Set Test Mode (command only accepted coming from IP 192.168.1.100)
	/*
	public function SetTestMode()
	{
		$command = '{"system":{"set_test_mode":{"enable":1}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}
	*/

	// WLAN Commands
	// ========================================

	// Scan for list of available APs
	public function ScanAP()
	{
		$command = '{"netif":{"get_scaninfo":{"refresh":1}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Connect to AP with given SSID and Password
	public function ConnectAP(string $ssid, string $password)
	{
		$command = '{"netif":{"set_stainfo":{"ssid":"' . $ssid . '","password":"' . $password . '","key_type":3}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Cloud Commands
	// ========================================

	// Get Cloud Info (Server, Username, Connection Status)
	public function GetCloudInfo()
	{
		$command = '{"cnCloud":{"get_info":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Get Firmware List from Cloud Server
	public function GetFirmwareList()
	{
		$command = '{"cnCloud":{"get_intl_fw_list":{}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Set Server URL
	public function SetServerURL(string $url)
	{
		// {"cnCloud":{"set_server_url":{"server":"devs.tplinkcloud.com"}}}
		$command = '{"cnCloud":{"set_server_url":{"server":"' . $url . '"}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Time Commands
	// ========================================

	// Get Time
	public function GetTime()
	{
		$command = '{"time":{"get_time":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Get Timezone
	public function GetTimezone()
	{
		$command = '{"time":{"get_timezone":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Set Timezone
	public function SetTimezone()
	{
		$command = '{"time":{"set_timezone":{"year":2016,"month":1,"mday":1,"hour":10,"min":10,"sec":10,"index":42}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Schedule Commands
	// (action to perform regularly on given weekdays)
	// ========================================

	// Get Next Scheduled Action
	public function GetNextScheduledAction()
	{
		$command = '{"schedule":{"get_next_action":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Get Schedule Rules List
	public function GetScheduleRulesList()
	{
		$command = '{"schedule":{"get_rules":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Add New Schedule Rule
	/*
	public function AddNewScheduleRule()
	{
		// {"schedule":{"add_rule":{"stime_opt":0,"wday":[1,0,0,1,1,0,0],"smin":1014,"enable":1,"repeat":1,"etime_opt":-1,"name":"lights on","eact":-1,"month":0,"sact":1,"year":0,"longitude":0,"day":0,"force":0,"latitude":0,"emin":0},"set_overall_enable":{"enable":1}}}
		$command = '{"schedule":{"add_rule":{"stime_opt":0,"wday":[1,0,0,1,1,0,0],"smin":1014,"enable":1,"repeat":1,"etime_opt":-1,"name":"lights on","eact":-1,"month":0,"sact":1,"year":0,"longitude":0,"day":0,"force":0,"latitude":0,"emin":0},"set_overall_enable":{"enable":1}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Edit Schedule Rule with given ID
	public function EditScheduleRule(string $id)
	{
		// {"schedule":{"edit_rule":{"stime_opt":0,"wday":[1,0,0,1,1,0,0],"smin":1014,"enable":1,"repeat":1,"etime_opt":-1,"id":"4B44932DFC09780B554A740BC1798CBC","name":"lights on","eact":-1,"month":0,"sact":1,"year":0,"longitude":0,"day":0,"force":0,"latitude":0,"emin":0}}}
		$command = '{"schedule":{"edit_rule":{"stime_opt":0,"wday":[1,0,0,1,1,0,0],"smin":1014,"enable":1,"repeat":1,"etime_opt":-1,"id":"'.$id.'","name":"lights on","eact":-1,"month":0,"sact":1,"year":0,"longitude":0,"day":0,"force":0,"latitude":0,"emin":0}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Delete Schedule Rule with given ID
	public function DeleteScheduleRule(string $id)
	{
		// {"schedule":{"delete_rule":{"id":"4B44932DFC09780B554A740BC1798CBC"}}}
		$command = '{"schedule":{"delete_rule":{"id":"'.$id.'"}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Delete All Schedule Rules and Erase Statistics
	public function DeleteAllScheduleRules()
	{
		// {"schedule":{"delete_all_rules":null,"erase_runtime_stat":null}}
		$command = '{"schedule":{"delete_all_rules":null,"erase_runtime_stat":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}
	*/

	// Countdown Rule Commands
	// (action to perform after number of seconds)

	// Get Rule (only one allowed)
	public function GetRule()
	{
		$command = '{"count_down":{"get_rules":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Add New Countdown Rule
	public function AddNewCountdownRule(int $delay, string $name)
	{
		// {"count_down":{"add_rule":{"enable":1,"delay":1800,"act":1,"name":"turn on"}}}
		$command = '{"count_down":{"add_rule":{"enable":1,"delay":' . $delay . ',"act":1,"name":"' . $name . '"}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Edit Countdown Rule with given ID
	public function EditCountdownRule(string $id, int $delay, string $name)
	{
		// {"count_down":{"edit_rule":{"enable":1,"id":"7C90311A1CD3227F25C6001D88F7FC13","delay":1800,"act":1,"name":"turn on"}}}
		$command = '{"count_down":{"edit_rule":{"enable":1,"id":"' . $id . '","delay":' . $delay . ',"act":1,"name":"' . $name . '"}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Delete Countdown Rule with given ID
	public function DeleteCountdownRule(string $id)
	{
		// {"count_down":{"delete_rule":{"id":"7C90311A1CD3227F25C6001D88F7FC13"}}}
		$command = '{"count_down":{"delete_rule":{"id":"' . $id . '"}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Delete All Coundown Rules
	public function DeleteAll()
	{
		// {"count_down":{"delete_all_rules":null}}
		$command = '{"count_down":{"delete_all_rules":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Anti-Theft Rule Commands (aka Away Mode)
	// (period of time during which device will be randomly turned on and off to deter thieves)
	// ========================================

	// Get Anti-Theft Rules List
	public function GetAntiTheftRules()
	{
		$command = '{"anti_theft":{"get_rules":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Delete All Anti-Theft Rules
	public function DeleteAllAntiTheftRules()
	{
		$command = '{"anti_theft":{"delete_all_rules":null}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Add New Anti-Theft Rule
	/*
	public function AddNewAntiTheftRule()
	{
		// {"anti_theft":{"add_rule":{"stime_opt":0,"wday":[0,0,0,1,0,1,0],"smin":987,"enable":1,"frequency":5,"repeat":1,"etime_opt":0,"duration":2,"name":"test","lastfor":1,"month":0,"year":0,"longitude":0,"day":0,"latitude":0,"force":0,"emin":1047},"set_overall_enable":1}}
		$command = '{"anti_theft":{"add_rule":{"stime_opt":0,"wday":[0,0,0,1,0,1,0],"smin":987,"enable":1,"frequency":5,"repeat":1,"etime_opt":0,"duration":2,"name":"test","lastfor":1,"month":0,"year":0,"longitude":0,"day":0,"latitude":0,"force":0,"emin":1047},"set_overall_enable":1}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	// Edit Anti-Theft Rule with given ID
	public function EditAntiTheftRule()
	{
		$command = '{"anti_theft":{"edit_rule":{"stime_opt":0,"wday":[0,0,0,1,0,1,0],"smin":987,"enable":1,"frequency":5,"repeat":1,"etime_opt":0,"id":"E36B1F4466B135C1FD481F0B4BFC9C30","duration":2,"name":"test","lastfor":1,"month":0,"year":0,"longitude":0,"day":0,"latitude":0,"force":0,"emin":1047},"set_overall_enable":1}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}
	*/

	// Delete Anti-Theft Rule with given ID
	public function DeleteAntiTheftRule(string $id)
	{
		$command = '{"anti_theft":{"delete_rule":{"id":"' . $id . '"}}}';
		$result = $this->SendToTPLink($command);
		return $result;
	}

	public function ReceiveData($JSONString)
	{
		$data = json_decode($JSONString);
		$objectid = $data->Buffer->objectid;
		$values = $data->Buffer->values;
		$valuesjson = json_encode($values);
		if (($this->InstanceID) == $objectid) {
			//Parse and write values to our variables
			//$this->WriteValues($valuesjson);
		}
	}

	protected function is_valid_localdomain($url)
	{

		$validation = FALSE;
		/*Parse URL*/
		$urlparts = parse_url(filter_var($url, FILTER_SANITIZE_URL));
		/*Check host exist else path assign to host*/
		if (!isset($urlparts['host'])) {
			$urlparts['host'] = $urlparts['path'];
		}

		if ($urlparts['host'] != '') {
			/*Add scheme if not found*/
			if (!isset($urlparts['scheme'])) {
				$urlparts['scheme'] = 'http';
			}
			/*Validation*/
			if (checkdnsrr($urlparts['host'], 'A') && in_array($urlparts['scheme'], array('http', 'https')) && ip2long($urlparts['host']) === FALSE) {
				$urlparts['host'] = preg_replace('/^www\./', '', $urlparts['host']);
				$url = $urlparts['scheme'] . '://' . $urlparts['host'] . "/";

				if (filter_var($url, FILTER_VALIDATE_URL) !== false && @get_headers($url)) {
					$validation = TRUE;
				}
			}
		}

		if (!$validation) {
			//echo $url." Its Invalid Domain Name.";
			$domaincheck = false;
			return $domaincheck;
		} else {
			//echo $url." is a Valid Domain Name.";
			$domaincheck = true;
			return $domaincheck;
		}

	}

	// gui on off knapp kalla denna funktion
	public function RequestAction($Ident, $Value)
	{
		IPS_LogMessage("SharpTV", "598 GUI On Off "); // JH
        switch ($Ident) {
			case "State":
				$varid = $this->GetIDForIdent("State");
				SetValue($varid, $Value);
				if ($Value) {
					$this->PowerOn();
				} else {
					$this->PowerOff();
				}
				break;
			default:
				$this->SendDebug("Request Action:", "Invalid ident", 0);
		}
	}

	protected function GetLEDState()
	{
		$state = $this->ReadPropertyBoolean("ledoff");
		if($state)
		{
			$led_state = "on";
		}
		else{
			$led_state = "off";
		}
		return $led_state;
	}

	//Profile

	/**
	 * register profiles
	 * @param $Name
	 * @param $Icon
	 * @param $Prefix
	 * @param $Suffix
	 * @param $MinValue
	 * @param $MaxValue
	 * @param $StepSize
	 * @param $Digits
	 * @param $Vartype
	 */
	protected function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype)
	{

		if (!IPS_VariableProfileExists($Name)) {
			IPS_CreateVariableProfile($Name, $Vartype); // 0 boolean, 1 int, 2 float, 3 string,
		} else {
			$profile = IPS_GetVariableProfile($Name);
			if ($profile['ProfileType'] != $Vartype) {
				$this->_debug('profile', 'Variable profile type does not match for profile ' . $Name);
			}
		}

		IPS_SetVariableProfileIcon($Name, $Icon);
		IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
		IPS_SetVariableProfileDigits($Name, $Digits); //  Nachkommastellen
		IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize); // string $ProfilName, float $Minimalwert, float $Maximalwert, float $Schrittweite
	}

	/**
	 * register profile association
	 * @param $Name
	 * @param $Icon
	 * @param $Prefix
	 * @param $Suffix
	 * @param $MinValue
	 * @param $MaxValue
	 * @param $Stepsize
	 * @param $Digits
	 * @param $Vartype
	 * @param $Associations
	 */
	protected function RegisterProfileAssociation($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype, $Associations)
	{
		if (is_array($Associations) && sizeof($Associations) === 0) {
			$MinValue = 0;
			$MaxValue = 0;
		}
		$this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype);

		if (is_array($Associations)) {
			foreach ($Associations AS $Association) {
				IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
			}
		} else {
			$Associations = $this->$Associations;
			foreach ($Associations AS $code => $association) {
				IPS_SetVariableProfileAssociation($Name, $code, $this->Translate($association), $Icon, -1);
			}
		}

	}

	/**
	 * send debug log
	 * @param string $notification
	 * @param string $message
	 * @param int $format 0 = Text, 1 = Hex
	 */
	private function _debug(string $notification = NULL, string $message = NULL, $format = 0)
	{
		$this->SendDebug($notification, $message, $format);
	}

	/***********************************************************
	 * Configuration Form
	 ***********************************************************/

	/**
	 * build configuration form
	 * @return string
	 */
	public function GetConfigurationForm()
	{
		// return current form
		return json_encode([
			'elements' => $this->FormHead(),
			'actions' => $this->FormActions(),
			'status' => $this->FormStatus()
		]);
	}

	/**
	 * return form configurations on configuration step
	 * @return array
	 */
	protected function FormHead()
	{
		$model = $this->ReadPropertyInteger("modelselection");
		$softwareversion = $this->ReadPropertyString("softwareversion");
		$form = [
			[
				'type' => 'Label',
				'caption' => 'TP Link HS type'
			],
			[
				'type' => 'Select',
				'name' => 'modelselection',
				'caption' => 'model',
				'options' => [
					[
						'label' => 'HS100',
						'value' => 1
					],
					[
						'label' => 'HS110',
						'value' => 2
					]
				]

			],
			[
				'type' => 'Label',
				'caption' => 'Sharp Aquos TV ip address'
			],
			[
				'name' => 'Ip',
				'type' => 'ValidationTextBox',
				'caption' => 'IP adress'
			],
            [
				'type' => 'Label',
				'caption' => 'Sharp Aquos TV device port'
			],
			[
				'name' => 'Port',
				'type' => 'ValidationTextBox',
				'caption' => 'Port'
			],
            [
				'type' => 'Label',
				'caption' => 'TP Link HS device state update interval'
			],
			[
				'name' => 'stateinterval',
				'type' => 'IntervalBox',
				'caption' => 'seconds'
			]
		];
		if ($model == 2) {
			$form = array_merge_recursive(
				$form,
				[
					[
						'type' => 'Label',
						'caption' => 'TP Link HS device system info update interval'
					],
					[
						'name' => 'systeminfointerval',
						'type' => 'IntervalBox',
						'caption' => 'seconds'
					]
				]
			);
		}
		if ($softwareversion == "") {
			$form = array_merge_recursive(
				$form,
				[
					[
						'type' => 'Label',
						'caption' => 'TP Link HS get system information'
					],
					[
						'type' => 'Button',
						'caption' => 'Get system info',
						'onClick' => 'SharpTV_WriteSystemInfo($id);'
					]
				]
			);
		}
		else{
			$form = array_merge_recursive(
				$form,
				[
					[
						'type' => 'Label',
						'caption' => 'Data is from the TP Link HS device for information, change settings in the kasa app'
					],
					[
						'type' => 'List',
						'name' => 'TPLinkInformation',
						'caption' => 'TP Link HS device information',
						'rowCount' => 2,
						'add' => false,
						'delete' => false,
						'sort' => [
							'column' => 'model',
							'direction' => 'ascending'
						],
						'columns' => [
							[
								'name' => 'model',
								'caption' => 'model',
								'width' => '100px',
								'visible' => true
							],
							[
								'name' => 'softwareversion',
								'caption' => 'software version',
								'width' => '150px',
							],
							[
								'name' => 'hardwareversion',
								'caption' => 'hardware version',
								'width' => '150px',
							],
							[
								'name' => 'type',
								'caption' => 'type',
								'width' => 'auto',
							],
							[
								'name' => 'mac',
								'caption' => 'mac',
								'width' => '150px',
							],
							[
								'name' => 'deviceid',
								'caption' => 'device id',
								'width' => '200px',
							],
							[
								'name' => 'hardwareid',
								'caption' => 'hardware id',
								'width' => '200px',
							],
							[
								'name' => 'firmwareid',
								'caption' => 'firmware id',
								'width' => '200px',
							],
							[
								'name' => 'oemid',
								'caption' => 'oem id',
								'width' => '200px',
							],
							[
								'name' => 'alias',
								'caption' => 'alias',
								'width' => '150px',
							],
							[
								'name' => 'devicename',
								'caption' => 'device name',
								'width' => '190px',
							],
							[
								'name' => 'rssi',
								'caption' => 'rssi',
								'width' => '50px',
							],
							[
								'name' => 'ledoff',
								'caption' => 'led state',
								'width' => '95px',
							],
							[
								'name' => 'latitude',
								'caption' => 'latitude',
								'width' => '110px',
							],
							[
								'name' => 'longitude',
								'caption' => 'longitude',
								'width' => '110px',
							]
						],
						'values' => [
							[
								'model' => $this->ReadPropertyString("model"),
								'softwareversion' => $this->ReadPropertyString("softwareversion"),
								'hardwareversion' => $this->ReadPropertyFloat("hardwareversion"),
								'type' => $this->ReadPropertyString("type"),
								'mac' => $this->ReadPropertyString("mac"),
								'deviceid' => $this->ReadPropertyString("deviceid"),
								'hardwareid' => $this->ReadPropertyString("hardwareid"),
								'firmwareid' => $this->ReadPropertyString("firmwareid"),
								'oemid' => $this->ReadPropertyString("oemid"),
								'alias' => $this->ReadPropertyString("alias"),
								'devicename' => $this->ReadPropertyString("devicename"),
								'rssi' => $this->ReadPropertyInteger("rssi"),
								'ledoff' => $this->GetLEDState(),
								'latitude' => $this->ReadPropertyFloat("latitude"),
								'longitude' => $this->ReadPropertyFloat("longitude")
							]]
					]
				]
			);
		}
		return $form;
	}

	/**
	 * return form actions by token
	 * @return array
	 */
	protected function FormActions()
	{
		$form = [
			[
				'type' => 'Label',
				'caption' => 'TP Link HS device'
			],
			[
				'type' => 'Label',
				'caption' => 'TP Link HS get system information'
			],
			[
				'type' => 'Button',
				'caption' => 'Get system info',
				'onClick' => 'SharpTV_WriteSystemInfo($id);'
			],
			[
				'type' => 'Label',
				'caption' => 'Sharp Aquos TV Power On'
			],
			[
				'type' => 'Button',
				'caption' => 'On',
				'onClick' => 'SharpTV_PowerOn($id);' //JHTPLHS
			],
			[
				'type' => 'Label',
				'caption' => 'Sharp Aquos TV Power Off'
			],
			[
				'type' => 'Button',
				'caption' => 'Off',
				'onClick' => 'SharpTV_PowerOff($id);'
			],
			[
				'type' => 'Label',
				'caption' => 'Reset Work'
			],
			[
				'type' => 'Button',
				'caption' => 'Reset Work',
				'onClick' => 'SharpTV_ResetWork($id);'
			]
		];
		return $form;
	}

	/**
	 * return from status
	 * @return array
	 */
	protected function FormStatus()
	{
		$form = [
			[
				'code' => 101,
				'icon' => 'inactive',
				'caption' => 'Creating instance.'
			],
			[
				'code' => 102,
				'icon' => 'active',
				'caption' => 'instance created.'
			],
			[
				'code' => 104,
				'icon' => 'inactive',
				'caption' => 'interface closed.'
			],
			[
				'code' => 201,
				'icon' => 'inactive',
				'caption' => 'Please follow the instructions.'
			],
			[
				'code' => 202,
				'icon' => 'error',
				'caption' => 'special errorcode.'
			],
			[
				'code' => 203,
				'icon' => 'error',
				'caption' => 'IP Address is not valid.'
			]
		];

		return $form;
	}
}

?>
