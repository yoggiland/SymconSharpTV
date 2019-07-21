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
        
        $this->GetConfigurationForParent();// jh test
        
		//$model = $this->ReadPropertyInteger("modelselection");
        /*
		if ($model == 2) {
			$this->RegisterProfile('TPLinkHS.Milliampere', '', '', " mA", 0, 0, 0, 0, 2);

			$this->RegisterVariableFloat("Voltage", $this->Translate("Voltage"), "Volt.230", 2);
			$this->RegisterVariableFloat("Power", $this->Translate("Power"), "Watt.14490", 3);
			$this->RegisterVariableFloat("Current", $this->Translate("Electricity"), "TPLinkHS.Milliampere", 4);
			$this->RegisterVariableFloat("Work", $this->Translate("Work"), "Electricity", 5);
		}
        
        */
		//$this->ValidateConfiguration(); // temp avstangd
	}
    
    public function GetConfigurationForParent()
    {
        IPS_LogMessage("SharpTV", "GetConfigurationForParent called ");
        /*
		$ip = $this->ReadPropertyString("Ip");
		$port = $this->ReadPropertyString("Port");
        return "{\"Ip\": \"$ip\", \"Port\": \"$port\"}";
        */
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

	// gui on off knapp kalla denna funktion
	public function RequestAction($Ident, $Value)
	{
		IPS_LogMessage("SharpTV", "422 GUI On Off "); // JH
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
}
/***********************************************************
*       Sharp Aquos TV commands
************************************************************
Command sent to the TV is 8-charater long string ending with a carriage return (\r, 0D or <CR>).
Commands are padded with blank spaces between command and carriage return inorder to reach specifid leangth e.g. the power on command 'POWR1' is padded with 3 blank spaces and carriage return is added ('POWR1   \r').

commands that return numbers POWR? (0, 1), VOLM? (0-60), MUTE? (1, 2), OFTM? (0-150) timer, IAVD? ( ERR, 1-8) input

Remote cmd			Aquos cmd		Aquos TV response

POWER ON			'POWR1   '		'OK'		
POWER OFF			'POWR0   '		'OK'
POWER TOGGLE
POWER ?			'POWR?   '		'0'	for off
							'1'	for on
VOL UP				NA			Need to create workaround, first pull value,
VOL DOWN			NA			add or subtract (+1 -1) then set new value
VOL 0 (0-60)			'VOLM0   '		'OK'	set volume to 0
VOL 1 (0-60)			'VOLM1   '		'OK'	set volume to 1
...
VOL 15 (0-60)			'VOLM15  '		'OK'	set volume to 15
VOL ?				'VOLM?   '		'7'	e.g. if current volume is set to 7 (0-60)

MUTE ON			'MUTE1   '		'OK'
MUTE OFF			'MUTE2   '		'OK'
MUTE TOGGLE			'MUTE0   '		'OK'
MUTE ?				'MUTE?   '		'1'	for mute on
							'2' 	for mute off

INPUT EXT 1			'IAVD1   '		'OK'
INPUT EXT 2			'IAVD2   '		'OK'
INPUT EXT 3			'IAVD3   '		'OK'
INPUT HDMI 1			'IAVD4   '		'OK'
INPUT HDMI 2			'IAVD5   '		'OK'
INPUT HDMI 3			'IAVD6   '		'OK'
INPUT HDMI 4			'IAVD7   '		'OK'
INPUT PC			'IAVD8   '		'OK'
INPUT				'IAVD?   '		'ERR'	if no input is selected
							'1'	for EXT 1
							'2'	for EXT 2
							'3'	for EXT 3
							'4'	for EXT HDMI 1
							'5'	for EXT HDMI 2
							'6'	for EXT HDMI 3
							'7'	for EXT HDMI 4
							'8'	for EXT PC

Channel Up Analog		'CHUP    '		'OK'	channel-up
Channel Down Analog		'CHDW    '		'OK'	channel-down

Channel Up			'DTUP    '		'OK'	Digital channel-up
Channel Down			'DTDW    '		'OK'	Digital channel-down

SLEPP 30			'OFTM1   '		'OK'
SLEPP 60			'OFTM2   '		'OK'
SLEPP 90			'OFTM3   '		'OK'
SLEPP 120			'OFTM4   '		'OK'
SLEPP 150			'OFTM5   '		'OK'
SLEEP OFF			'OFTM0   '		'OK'
SLEPP ?			'OFTM?   '		'149'	e.g. if timer is at 149 minutes before turn off (0-150)

IPPOWERUP ENABLE		'RSPW2   '		'OK'	Enable power on (IP)

FIRMWARE			'SWVN1   '		'208E1204161'	(e.g.)	Firmware?
UNITNAME			'TVNM1   '		'My AQUOS TV'	(e.g.)	Unit name?
MODEL				'MNRD1   '		'LE831E'	(e.g.)	Model?

An incorrect command returns 'ERR'
All status commands returns 'ERR' if TV is off except 'POWR?   ' that returns '0'

Response code format

Normal response		'OK' '0', '1', '2', '0-60', '0-150' with '\r' at the end e.g.('OK\r')
Problem response		'ERR' with '\r' at the end (incorrect command)

*/
	
/***********************************************************
* misc to use later
***********************************************************/
// Set username and password, if fields are left blank no login is required
// Set port, default is 6002

// Open Menu -> Settings -> View Settings -> Network Settings -> IP control Settings -> ChangeSet your tv name, username, password, port

// user and password needs to be sent as one concatenated string for login to be successful
// $user . "\r" . $password . "\r"

/*
    After that you can send your commands so:
    echo -e -n 'user\x0dpassword\x0dIAVD4   \x0d' | \
    socat - tcp4:192.168.100.113:10002
    \x0d = Carriage Return 
*/

/*
1 Go to “Menu” > “Setup” > “View setting” >
“Network setup” > “IP Control setup” > select
“Change”.
2 To use IP Control, select “Enable”.
3 Set the device name.
4 Set your login ID and password.
5 Set the port to use with IP Control.
6 Confirm the settings, and then press “OK”.
*/
?>
