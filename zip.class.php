<?php
class zipfile
{
	/*
		zipfile class, for reading or writing .zip files
		See http://www.gamingg.net for more of my work
		Based on tutorial given by John Coggeshall at http://www.zend.com/zend/spotlight/creating-zip-files3.php
		Copyright (C) Joshua Townsend and licensed under the GPL
		Version 1.0
	*/
	var $datasec = array(); // array to store compressed data
	var $dirs = array(); // array of directories that have been created already
	var $ctrl_dir = array(); // central directory
	var $eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record
	var $old_offset = 0;
	var $basedir = ".";
	
	function create_dir($name) // Adds a directory to the zip with the name $name
	{
		$name = str_replace("\\", "/", $name);

		$fr = "\x50\x4b\x03\x04";
		$fr .= "\x0a\x00";	// version needed to extract
		$fr .= "\x00\x00";	// general purpose bit flag
		$fr .= "\x00\x00";	// compression method
		$fr .= "\x00\x00\x00\x00"; // last mod time and date

		$fr .= pack("V",0); // crc32
		$fr .= pack("V",0); //compressed filesize
		$fr .= pack("V",0); //uncompressed filesize
		$fr .= pack("v",strlen($name)); //length of pathname
		$fr .= pack("v", 0); //extra field length
		$fr .= $name;
		// end of "local file header" segment

		// no "file data" segment for path

		// "data descriptor" segment (optional but necessary if archive is not served as file)
		$fr .= pack("V",0); //crc32
		$fr .= pack("V",0); //compressed filesize
		$fr .= pack("V",0); //uncompressed filesize

		// add this entry to array
		$this->datasec[] = $fr;

		$new_offset = strlen(implode("", $this->datasec));

		// ext. file attributes mirrors MS-DOS directory attr byte, detailed
		// at http://support.microsoft.com/support/kb/articles/Q125/0/19.asp

		// now add to central record
		$cdrec = "\x50\x4b\x01\x02";
		$cdrec .="\x00\x00";	// version made by
		$cdrec .="\x0a\x00";	// version needed to extract
		$cdrec .="\x00\x00";	// general purpose bit flag
		$cdrec .="\x00\x00";	// compression method
		$cdrec .="\x00\x00\x00\x00"; // last mod time and date
		$cdrec .= pack("V",0); // crc32
		$cdrec .= pack("V",0); //compressed filesize
		$cdrec .= pack("V",0); //uncompressed filesize
		$cdrec .= pack("v", strlen($name) ); //length of filename
		$cdrec .= pack("v", 0 ); //extra field length
		$cdrec .= pack("v", 0 ); //file comment length
		$cdrec .= pack("v", 0 ); //disk number start
		$cdrec .= pack("v", 0 ); //internal file attributes
		$cdrec .= pack("V", 16 ); //external file attributes  - 'directory' bit set

		$cdrec .= pack("V", $this->old_offset); //relative offset of local header
		$this->old_offset = $new_offset;

		$cdrec .= $name;
		// optional extra field, file comment goes here
		// save to array
		$this->ctrl_dir[] = $cdrec;
		$this->dirs[] = $name;
	}

	function create_file($data, $name,$time=0) // Adds a file to the path specified by $name with the contents $data
	{
 	$name     = str_replace('\\', '/', $name);

        $dtime    = dechex($this->unix2DosTime($time));
        $hexdtime = '\x' . $dtime[6] . $dtime[7]
                  . '\x' . $dtime[4] . $dtime[5]
                  . '\x' . $dtime[2] . $dtime[3]
                  . '\x' . $dtime[0] . $dtime[1];
        eval('$hexdtime = "' . $hexdtime . '";');

        $fr   = "\x50\x4b\x03\x04";
        $fr   .= "\x14\x00";            // ver needed to extract
        $fr   .= "\x00\x00";            // gen purpose bit flag
        $fr   .= "\x08\x00";            // compression method
        $fr   .= $hexdtime;             // last mod time and date

        // "local file header" segment
        $unc_len = strlen($data);
        $crc     = crc32($data);
        $zdata   = gzcompress($data);
        $zdata   = substr(substr($zdata, 0, strlen($zdata) - 4), 2); // fix crc bug
        $c_len   = strlen($zdata);
        $fr      .= pack('V', $crc);             // crc32
        $fr      .= pack('V', $c_len);           // compressed filesize
        $fr      .= pack('V', $unc_len);         // uncompressed filesize
        $fr      .= pack('v', strlen($name));    // length of filename
        $fr      .= pack('v', 0);                // extra field length
        $fr      .= $name;

        // "file data" segment
        $fr .= $zdata;

        // "data descriptor" segment (optional but necessary if archive is not
        // served as file)
        $fr .= pack('V', $crc);                 // crc32
        $fr .= pack('V', $c_len);               // compressed filesize
        $fr .= pack('V', $unc_len);             // uncompressed filesize

        // add this entry to array
        $this -> datasec[] = $fr;

        // now add to central directory record
        $cdrec = "\x50\x4b\x01\x02";
        $cdrec .= "\x00\x00";                // version made by
        $cdrec .= "\x14\x00";                // version needed to extract
        $cdrec .= "\x00\x00";                // gen purpose bit flag
        $cdrec .= "\x08\x00";                // compression method
        $cdrec .= $hexdtime;                 // last mod time & date
        $cdrec .= pack('V', $crc);           // crc32
        $cdrec .= pack('V', $c_len);         // compressed filesize
        $cdrec .= pack('V', $unc_len);       // uncompressed filesize
        $cdrec .= pack('v', strlen($name) ); // length of filename
        $cdrec .= pack('v', 0 );             // extra field length
        $cdrec .= pack('v', 0 );             // file comment length
        $cdrec .= pack('v', 0 );             // disk number start
        $cdrec .= pack('v', 0 );             // internal file attributes
        $cdrec .= pack('V', 32 );            // external file attributes - 'archive' bit set

        $cdrec .= pack('V', $this -> old_offset ); // relative offset of local header
        $this -> old_offset += strlen($fr);

        $cdrec .= $name;

        // optional extra field, file comment goes here
        // save to central directory
        $this -> ctrl_dir[] = $cdrec; 
	}
	
    /**
     * Converts an Unix timestamp to a four byte DOS date and time format (date
     * in high two bytes, time in low two bytes allowing magnitude comparison).
     *
     * @param  integer  the current Unix timestamp
     *
     * @return integer  the current date in a four byte DOS format
     *
     * @access private
     */
    function unix2DosTime($unixtime = 0) {
        $timearray = ($unixtime == 0) ? getdate() : getdate($unixtime);

        if ($timearray['year'] < 1980) {
            $timearray['year']    = 1980;
            $timearray['mon']     = 1;
            $timearray['mday']    = 1;
            $timearray['hours']   = 0;
            $timearray['minutes'] = 0;
            $timearray['seconds'] = 0;
        } // end if

        return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) |
                ($timearray['hours'] << 11) | ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
    } // end of the 'unix2DosTime()' method 

	

	function add_file($file, $dir = ".", $file_blacklist = array(), $ext_blacklist = array())
	{
		$file = str_replace("\\", "/", $file);
		$dir = str_replace("\\", "/", $dir);

		if(strpos($file, "/") !== false)
		{
			$dira = explode("/", "{$dir}/{$file}");
			$file = array_shift($dira);
			$dir = implode("/", $dira);
			unset($dira);
		}

		while(substr($dir, 0, 2) == "./")
		{
			$dir = substr($dir, 2);
		}
		while(substr($file, 0, 2) == "./")
		{
			$file = substr($file, 2);
		}
		if(!in_array($dir, $this->dirs))
		{
			if($dir == ".")
			{
				$this->create_dir("./");
			}
			$this->dirs[] = $dir;
		}
		if(in_array($file, $file_blacklist))
		{
			return true;
		}
		foreach($ext_blacklist as $ext)
		{
			if(substr($file, -1 - strlen($ext)) == ".{$ext}")
			{
				return true;
			}
		}

		$filepath = (($dir && $dir != ".") ? "{$dir}/" : "").$file;
		if(is_dir("{$this->basedir}/{$filepath}"))
		{
			$dh = opendir("{$this->basedir}/{$filepath}");
			while(($subfile = readdir($dh)) !== false)
			{
				if($subfile != "." && $subfile != "..")
				{
					$this->add_file($subfile, $filepath, $file_blacklist, $ext_blacklist);
				}
			}
			closedir($dh);
		}
		else
		{
			$this->create_file(implode("", file("{$this->basedir}/{$filepath}")), $filepath);
		}

		return true;
	}


	function zipped_file() // return zipped file contents
	{
		$data = implode("", $this->datasec);
		$ctrldir = implode("", $this->ctrl_dir);

		return $data.
				$ctrldir.
				$this->eof_ctrl_dir.
				pack("v", sizeof($this->ctrl_dir)). // total number of entries "on this disk"
				pack("v", sizeof($this->ctrl_dir)). // total number of entries overall
				pack("V", strlen($ctrldir)). // size of central dir
				pack("V", strlen($data)). // offset to start of central dir
				"\x00\x00"; // .zip file comment length
	}
}
?>
