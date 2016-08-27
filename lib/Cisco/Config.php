<?php
namespace Cisco;

class Config {
    public $Config;
    
    protected $Opts;
    protected $Blocks;
    
    public function __construct()
    {
        $this->Config = array();
        $this->Opts = array();
        $this->Blocks = array();
    }
    
    public function addLine($Line)
    {
        $this->Config[] = $Line;
    }
    
    /**
     * Stable mergesort
     * @param type $array
     * @param type $cmp_function
     * @return type
     */
    public function mergesort(&$array, $cmp_function = 'strcmp')
    { 
        // Adapted from http://php.net/manual/en/function.usort.php
        // Arrays of size < 2 require no action. 
        if (count($array) < 2) return; 
        // Split the array in half 
        $halfway = count($array) / 2; 
        $array1 = array_slice($array, 0, $halfway); 
        $array2 = array_slice($array, $halfway); 
        // Recurse to sort the two halves 
        self::mergesort($array1, $cmp_function); 
        self::mergesort($array2, $cmp_function); 
        // If all of $array1 is <= all of $array2, just append them. 
        if (call_user_func($cmp_function, end($array1), $array2[0]) < 1) { 
            $array = array_merge($array1, $array2); 
            return; 
        } 
        // Merge the two sorted arrays into a single sorted array 
        $array = array(); 
        $ptr1 = $ptr2 = 0; 
        while ($ptr1 < count($array1) && $ptr2 < count($array2)) { 
            if (call_user_func($cmp_function, $array1[$ptr1], $array2[$ptr2]) < 1) { 
                $array[] = $array1[$ptr1++]; 
            } 
            else { 
                $array[] = $array2[$ptr2++]; 
            } 
        } 
        // Merge the remainder 
        while ($ptr1 < count($array1)) $array[] = $array1[$ptr1++]; 
        while ($ptr2 < count($array2)) $array[] = $array2[$ptr2++]; 
        return; 
    }

    
    
    
    public function sortBlocks()
    {
        // Can't use usort here as it is not stable.
        $this->mergesort($this->Blocks, 'Cisco\\Config::compareBlock');
    }
    
    static public function compareBlock(ConfBlock $a, ConfBlock $b)
    {
        if($a->Pos > $b->Pos) {
            return 1;
        } elseif ($a->Pos > $b->Pos) {
            return -1;
        } else {
            return 0;
        }
    }
    
    public function getOptVal($Name)
    {
        return $this->Opts[$Name]->GetValue();
    }
    
    protected function addOpt($Name, $Value, $Type = 'text', $Description = '', $Group = 'Default group')
    {
        /*$this->Opts[$Name]['Value'] = $Value;
        $this->Opts[$Name]['Type'] = $Type;
        $this->Opts[$Name]['Description'] = $Description;
        $this->Opts[$Name]['Group'] = $Group;*/
        $this->Opts[$Name] = new ConfigOption($Name, $Value, $Type, $Description, $Group);
    }
    
    /**
     * Override an already defined option's default value
     * @param type $Name The name of the option
     * @param type $Value Default value to set to
     */
    public function setOptDefaultValue($Name, $Value)
    {
        $this->Opts[$Name]->SetDefaultValue($Value);
    }
    
    
    public function setOptVal($Name, $Value)
    {
        $this->Opts[$Name]->setValue($Value);
    }
    
    public function getOpts()        
    {
        return $this->Opts;
    }
    
    public function getOptsByGroup()
    {
        $Result = array();
        foreach($this->getOpts() as $OptName => $Opt) {
            $Result[$Opt->GetGroup()][$OptName] = $Opt;
        }
        return $Result;
    }
    
    public function getConfig()
    {
        $Ret = '!! Generated by ciscoconf.net - ' . date('r') . "\n";
        if (@count($this->Config) > 0)
        {
            $Ret .= implode("\n", $this->Config) . "\n";
        }
        foreach($this->Blocks as $Block) {
            $Ret .= "!\n";
            $Ret .= (string) $Block;
        }
        return $Ret;
    }
    
    public function &addBlock($BlockInit, $Pos = ConfBlock::POS_END, $Flat = false)
    {
        $this->Blocks[] = $Block = new ConfBlock($BlockInit, $Pos, $Flat);
        return $Block;
    }
    
    public function &newBlock($BlockInit, $Pos = ConfBlock::POS_END, $Flat = false)
    {    
        $Block = new ConfBlock($BlockInit, $Pos, $Flat);
        return $Block;
    }

    /**
     * Convert a CIDR notation subnet mask (1-32) to a classic subnet mask
     * notation (255.255.0.0, etc...)
     * @param type $CIDR
     * @param type $Reverse
     * @return string
     */
    public function convertCIDRToSubnetMask($CIDR, $Reverse = false)
    {
        if($Reverse) {
            $Str = str_pad('', $CIDR, '0');
            $Str = str_pad($Str, 32, '1');
        } else {
            $Str = str_pad('', $CIDR, '1');
            $Str = str_pad($Str, 32, '0');
        }

        $Decimal = bindec(substr($Str, 0, 8));
        $Decimal .= '.' . bindec(substr($Str, 8, 8));
        $Decimal .= '.' . bindec(substr($Str, 16, 8));
        $Decimal .= '.' . bindec(substr($Str, 24, 8));

        return $Decimal;
    }
    
    /**
     * Split an IP address
     * @param String $IPWithSM IP address with subnetmask (192.168.0.1/24 or 192.168.0.1/255.255.255.0)
     * @return array ip & prefix
     */
    public function splitIP($IPWithSM)
    {
        list($IP, $Prefix) = explode('/', $IPWithSM);
        return array(
            'ip' => $IP,
            'prefix' => $Prefix
        );
    }
    
    /**
     * Get the network address for given IP
     * @param type The IP address
     * @param type The CIDR prefix
     * @return string Network address
     */
    public function getNetAddrFromIP($IP, $CIDR)
    {
        return long2ip(ip2long($IP) & ip2long($this->convertCIDRToSubnetMask($CIDR)));
    }
    
    public function nextIP($IP)
    {
        return long2ip((ip2long($IP) + 1));
    }

}
