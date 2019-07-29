<?
//
// https://github.com/Wolbolar/IPSymconTPLinkHS110
// https://github.com/nik78476/SymconMeteoblue

class SharpTV extends IPSModule // Sharp Aquos TV
{
	public function Create()
	{
		//Never delete this line!
		parent::Create();
        
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        
        // Configuration Values
		
		$this->RegisterPropertyString("ID", "");
		$this->RegisterPropertyString("Password", "");
		
        $this->RegisterPropertyString("Ip", "192.168.1.102"); // Aquos TV 192.168.1.64
        $this->RegisterPropertyString("Port", 10002);
        
        // Register Script
		
		$this->RegisterScript("Power", "Power ?", "<? SharpTV_Power(".$this->InstanceID.");", 0);
        $this->RegisterScript("PowerOn", "Power On", "<? SharpTV_PowerOn(".$this->InstanceID.");", 1);
		$this->RegisterScript("PowerOff", "Power Off", "<? SharpTV_PowerOff(".$this->InstanceID.");", 2); // int is order listed
		
		$this->RegisterScript("GetVolume", "Get Volume", "<? SharpTV_GetVolume(".$this->InstanceID.");", 3);
		$this->RegisterScript("SetVolume", "Set Volume", "<? SharpTV_SetVolume(".$this->InstanceID.");", 4);

        $this->RegisterScript("VolumeUp", "Volume Up", "<? SharpTV_VolumeUp(".$this->InstanceID.");", 5);
        
		$this->RegisterScript("Login", "Login", "<? SharpTV_Login(".$this->InstanceID.");", 6);
		
		//we will wait until the kernel is ready
		$this->RegisterMessage(0, IPS_KERNELMESSAGE);
	}

	public function ApplyChanges()
	{
		//Never delete this line!
		parent::ApplyChanges();
        
        //IPS_LogMessage("SharpTV", "46 ApplyChanges called");
        
		$this->RegisterVariableBoolean("State", "Status", "~Switch", 1);
		$this->EnableAction("State");
		
		// Volume
		$this->RegisterVariableInteger("Volume", "Volume", "~Intensity.100", 2);
		$this->EnableAction("Volume");
		
        // test
        //$this->RegisterVariableInteger('Volume2', 'Volume2', 'Intensity.60');
        //$this->EnableAction('Volume2');
        //
        
		// ID and Password
        $id = $this->ReadPropertyString('ID');
        $password = $this->ReadPropertyString('Password');		
		
        // Ip and Port
        $ip = $this->ReadPropertyString('Ip');
        $port = $this->ReadPropertyString('Port');
        
        $this->SetEGPMSLANTimerInterval(); // test
        
        //$this->Update();
        }

////////////
    	protected function SetEGPMSLANTimerInterval()
	{
		IPS_LogMessage("SharpTV", "SetEGPMSLANTimerInterval calld " );
        $update_interval = $this->ReadPropertyInteger('UpdateInterval');
		$Interval = $update_interval * 1000;
		$this->SetTimerInterval("Update", $Interval);
	}
///////////
        public function Update() // not in use
        {
            IPS_LogMessage("SharpTV", "Update called ");
            
            //Ip and Port update // test
            $ip = $this->ReadPropertyString('Ip');
            $port = $this->ReadPropertyString('Port');
        }
		
		protected function SendToSharpTV($command) // e.g function PowerOn() calls this function
		{

			$id = $this->ReadPropertyString('ID');
			$password = $this->ReadPropertyString('Password');

			$ip = $this->ReadPropertyString('Ip');
			$port = $this->ReadPropertyString('Port');
			
			// test
			if (!$password == ""){
				
				IPS_LogMessage("SharpTV", "We have password ");
				
			}
			
			if (!$id == ""){
				
				IPS_LogMessage("SharpTV", "We have ID ");
				
			}
			
			// test end
			
			//Connect to Server
			$socket = stream_socket_client("{$ip}:{$port}", $errno, $errstr, 3); // Seconds until the connect should timeout (float e.g. 0.5)

			if (!$socket) {
				//echo "Unable to open\n";
				IPS_LogMessage("SharpTV", "Unable to open socket " );
				} else {
					
					IPS_LogMessage("SharpTV", "What command do we send to TV: " . $command );
					fwrite($socket, $command);
					
					//stream_set_timeout ( resource $stream , int $seconds [, int $microseconds = 0 ] ) : bool
					stream_set_timeout($socket, 3); // 10
					
					$buf = fread($socket, 1024); // 2000
					
					$info = stream_get_meta_data($socket);
					
					fclose($socket);
					
					if ($info['timed_out']) {
						IPS_LogMessage("SharpTV", "Send to connection timed out " );
						
						} else {
							
                        IPS_LogMessage("SharpTV", "Message recived back from TV: " . $buf);
                        return $buf;
					}
			}
}

	// Login	// $user . "\r" . $password . "\r"
	// /*
	public function Login() // NOT WORKING
	{
		
		$command = "ABCD    " . "\r" . "1234" . "\r";
		
		$result = $this->SendToSharpTV($command);
		
		return $result;
	}
	// */
	
	// Power ?
	public function Power()
	{
		$command = "POWR?   \r";
		$result = $this->SendToSharpTV($command);
		return $result;
	}
	
	// Power On
	public function PowerOn()
	{
        $command = "POWR1   \r";
		$result = $this->SendToSharpTV($command);
		return $result;
	}

	// Power Off
	public function PowerOff()
	{
		$command = "POWR0   \r";
		$result = $this->SendToSharpTV($command);
		return $result;
	}
	
	
		// Volume up
	public function VolumeUp()
	{
		//$result = GetVolume()
		//IPS_LogMessage("SharpTV", "Volume back: " . $result);
	}
	
	// Volume down
	public function VolumeDown()
	{
		//
	}
	
	// Get current volume
	public function GetVolume()
	{
        $command = "VOLM?   \r";
		$result = $this->SendToSharpTV($command);
		return $result;
	}
	
	// Set current volume
	public function SetVolume()
	{
        $command = "VOLM7   \r"; // temp 7
		$result = $this->SendToSharpTV($command);
		return $result;
	} 
	

	public function ReceiveData($JSONString) // not in use
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

	// GUI on off knapp calls this funktion
	public function RequestAction($Ident, $Value)
	{
		IPS_LogMessage("SharpTV", "212 RequestAction, GUI On Off "); // JH
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
				// båda fungerar
				// /*
				case "Volume":

					$this->SetValue($Ident, $Value);

					break;
				// */	
				//
			default:
				$this->SendDebug("Request Action:", "Invalid ident", 0);
		}
		//  båda fungerar
		/*
		switch($Ident) {

				case "Volume":

					$this->SetValue($Ident, $Value);

					break;

				default:

					throw new Exception("Invalid ident");

			} */
		//
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
