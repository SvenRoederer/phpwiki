<? rcs_id("$Id: wiki_ziplib.php3,v 1.3 2000-07-19 16:25:58 dairiki Exp $");

function warn ($msg)
{
  echo "<br><b>Warning:</b> " . htmlspecialchars($msg) . "<br>\n";
}

/**
 * GZIP stuff.
 *
 * All this is just to get around the fact that not all PHP's (even if
 * they have zlib support built in) have gz[un]compress().
 */
if (! function_exists('gzcompress')) {
  function gzip_cleanup () {
    global $gzip_tmpfile;
    
    if ($gzip_tmpfile)
	unlink($gzip_tmpfile);
  }

  function gzip_tempnam () {
    global $gzip_tmpfile;

    if (!$gzip_tmpfile)
      {
	//FIXME: does this work on non-unix machines?
	$gzip_tmpfile = tempnam("/tmp", "wkzip");
	register_shutdown_function("gzip_cleanup");
      }
    return $gzip_tmpfile;
  }

  function gzcompress ($data) {
    $filename = gzip_tempnam();
    if (!($fp = gzopen($filename, "wb")))
	die("gzopen failed");
    gzwrite($fp, $data, strlen($data));
    if (!gzclose($fp))
	die("gzclose failed");
    
    $size = filesize($filename);

    if (!($fp = fopen($filename, "rb")))
	die("fopen failed");
    if (!($z = fread($fp, $size)) || strlen($z) != $size)
	die("fread failed");
    if (!fclose($fp))
	die("fclose failed");

    unlink($filename);
    return $z;
  }

  function gzuncompress ($data) {
    $filename = gzip_tempnam();
    if (!($fp = fopen($filename, "wb")))
	die("fopen failed");
    fwrite($fp, $data, strlen($data));
    if (!fclose($fp))
	die("fclose failed");

    if (!($fp = gzopen($filename, "rb")))
	die("gzopen failed");
    while ($buf = gzread($fp, 4096))
	$unz .= $buf;
    if (!gzclose($fp))
	die("gzclose failed");

    unlink($filename);
    return $unz;
  }
}

/**
 * Functions to pack and unpack integers into DOS order byte strings.
 */
function zip_pack ($val, $len)
{
  $oval = $val;
  $packed = chr($val & 0xff);
  if (--$len)
    {
      $val >>= 8;
      $val &= 0x00ffffff;
      $packed .= chr($val & 0xff);
      while (--$len)
	  $packed .= chr(($val >>= 8) & 0xff);
    }
  if (($val & ~0xff) != 0)
      die("value ($oval) out of range");
  return $packed;
}

function zip_unpack ($str, $offset, $len)
{
  $val = 0;
  for ($i = $offset + $len - 1; $i >= $offset; $i--)
    {
      $val <<= 8;
      $val |= ord($str[$i]) & 0xff;
    }
  return $val;
}

      
/**
 * CRC32 computation.  Hacked from Info-zip's zip-2.3 source code.
 */
/* NOTE: The range of PHP ints seems to be -0x80000000 to 0x7fffffff.
 * So, had to munge these constants.
 */
$zip_crc_table = array (
     0x00000000,  0x77073096, -0x11f19ed4, -0x66f6ae46,  0x076dc419,
     0x706af48f, -0x169c5acb, -0x619b6a5d,  0x0edb8832,  0x79dcb8a4,
    -0x1f2a16e2, -0x682d2678,  0x09b64c2b,  0x7eb17cbd, -0x1847d2f9,
    -0x6f40e26f,  0x1db71064,  0x6ab020f2, -0x0c468eb8, -0x7b41be22,
     0x1adad47d,  0x6ddde4eb, -0x0b2b4aaf, -0x7c2c7a39,  0x136c9856,
     0x646ba8c0, -0x029d0686, -0x759a3614,  0x14015c4f,  0x63066cd9,
    -0x05f0c29d, -0x72f7f20b,  0x3b6e20c8,  0x4c69105e, -0x2a9fbe1c,
    -0x5d988e8e,  0x3c03e4d1,  0x4b04d447, -0x2df27a03, -0x5af54a95,
     0x35b5a8fa,  0x42b2986c, -0x2444362a, -0x534306c0,  0x32d86ce3,
     0x45df5c75, -0x2329f231, -0x542ec2a7,  0x26d930ac,  0x51de003a,
    -0x3728ae80, -0x402f9eea,  0x21b4f4b5,  0x56b3c423, -0x30456a67,
    -0x47425af1,  0x2802b89e,  0x5f058808, -0x39f3264e, -0x4ef416dc,
     0x2f6f7c87,  0x58684c11, -0x3e9ee255, -0x4999d2c3,  0x76dc4190,
     0x01db7106, -0x672ddf44, -0x102aefd6,  0x71b18589,  0x06b6b51f,
    -0x60401b5b, -0x17472bcd,  0x7807c9a2,  0x0f00f934, -0x69f65772,
    -0x1ef167e8,  0x7f6a0dbb,  0x086d3d2d, -0x6e9b9369, -0x199ca3ff,
     0x6b6b51f4,  0x1c6c6162, -0x7a9acf28, -0x0d9dffb2,  0x6c0695ed,
     0x1b01a57b, -0x7df70b3f, -0x0af03ba9,  0x65b0d9c6,  0x12b7e950,
    -0x74414716, -0x03467784,  0x62dd1ddf,  0x15da2d49, -0x732c830d,
    -0x042bb39b,  0x4db26158,  0x3ab551ce, -0x5c43ff8c, -0x2b44cf1e,
     0x4adfa541,  0x3dd895d7, -0x5b2e3b93, -0x2c290b05,  0x4369e96a,
     0x346ed9fc, -0x529877ba, -0x259f4730,  0x44042d73,  0x33031de5,
    -0x55f5b3a1, -0x22f28337,  0x5005713c,  0x270241aa, -0x41f4eff0,
    -0x36f3df7a,  0x5768b525,  0x206f85b3, -0x46992bf7, -0x319e1b61,
     0x5edef90e,  0x29d9c998, -0x4f2f67de, -0x3828574c,  0x59b33d17,
     0x2eb40d81, -0x4842a3c5, -0x3f459353, -0x12477ce0, -0x65404c4a,
     0x03b6e20c,  0x74b1d29a, -0x152ab8c7, -0x622d8851,  0x04db2615,
     0x73dc1683, -0x1c9cf4ee, -0x6b9bc47c,  0x0d6d6a3e,  0x7a6a5aa8,
    -0x1bf130f5, -0x6cf60063,  0x0a00ae27,  0x7d079eb1, -0x0ff06cbc,
    -0x78f75c2e,  0x1e01f268,  0x6906c2fe, -0x089da8a3, -0x7f9a9835,
     0x196c3671,  0x6e6b06e7, -0x012be48a, -0x762cd420,  0x10da7a5a,
     0x67dd4acc, -0x06462091, -0x71411007,  0x17b7be43,  0x60b08ed5,
    -0x29295c18, -0x5e2e6c82,  0x38d8c2c4,  0x4fdff252, -0x2e44980f,
    -0x5943a899,  0x3fb506dd,  0x48b2364b, -0x27f2d426, -0x50f5e4b4,
     0x36034af6,  0x41047a60, -0x209f103d, -0x579820ab,  0x316e8eef,
     0x4669be79, -0x349e4c74, -0x43997ce6,  0x256fd2a0,  0x5268e236,
    -0x33f3886b, -0x44f4b8fd,  0x220216b9,  0x5505262f, -0x3a45c442,
    -0x4d42f4d8,  0x2bb45a92,  0x5cb36a04, -0x3d280059, -0x4a2f30cf,
     0x2cd99e8b,  0x5bdeae1d, -0x649b3d50, -0x139c0dda,  0x756aa39c,
     0x026d930a, -0x63f6f957, -0x14f1c9c1,  0x72076785,  0x05005713,
    -0x6a40b57e, -0x1d4785ec,  0x7bb12bae,  0x0cb61b38, -0x6d2d7165,
    -0x1a2a41f3,  0x7cdcefb7,  0x0bdbdf21, -0x792c2d2c, -0x0e2b1dbe,
     0x68ddb3f8,  0x1fda836e, -0x7e41e933, -0x0946d9a5,  0x6fb077e1,
     0x18b74777, -0x77f7a51a, -0x00f09590,  0x66063bca,  0x11010b5c,
    -0x709a6101, -0x079d5197,  0x616bffd3,  0x166ccf45, -0x5ff51d88,
    -0x28f22d12,  0x4e048354,  0x3903b3c2, -0x5898d99f, -0x2f9fe909,
     0x4969474d,  0x3e6e77db, -0x512e95b6, -0x2629a524,  0x40df0b66,
     0x37d83bf0, -0x564351ad, -0x2144613b,  0x47b2cf7f,  0x30b5ffe9,
    -0x42420de4, -0x35453d76,  0x53b39330,  0x24b4a3a6, -0x452fc9fb,
    -0x3228f96d,  0x54de5729,  0x23d967bf, -0x4c9985d2, -0x3b9eb548,
     0x5d681b02,  0x2a6f2b94, -0x4bf441c9, -0x3cf3715f,  0x5a05df1b,
     0x2d02ef8d
);

function zip_crc32 ($str, $crc = 0) 
{
  global $zip_crc_table;
  $crc = ~$crc;
  for ($i = 0; $i < strlen($str); $i++)
      $crc = ( $zip_crc_table[($crc ^ ord($str[$i])) & 0xff]
               ^ (($crc >> 8) & 0xffffff) );
  return ~$crc;
}

function zip_deflate ($content)
{
  // Compress content, and suck information from gzip header.
  $z = gzcompress($content);

  if (substr($z,0,3) != "\037\213\010")
      die("Bad gzip magic");

  $gz_flags = ord($z[3]);
  if (($gz_flags & 0x3e) != 0)
      die(sprintf("Bad flags (0x%02x)", $gz_flags));

  // Suck OS type byte from gzip header. FIXME: this smells bad.
  $os_type = ord($z[9]);

  $gz_header_len = 10;
  $gz_data_len = strlen($z) - $gz_header_len - 8;
  if ($gz_data_len < 0)
      die("not enough gzip output?");

  $comptype = zip_unpack($z, 2, 1);
  $crc32 = zip_unpack($z, $gz_header_len + $gz_data_len, 4);

  return array(substr($z, $gz_header_len, $gz_data_len), // gzipped data
	       $crc32,		// crc
	       $comptype,	// compression type code
	       $os_type		// OS type
      );
}

function zip_inflate ($data, $crc32, $uncomp_size, $comp_type)
{
  // Reconstruct gzip header and ungzip the data.
  $head = "\037\213";		// Magic.
  $head .= chr($comp_type);
  $head .= "\0";		// flags
  $head .= zip_pack(time(), 4); // (Bogus) mtime.
  $head .= "\0";		// (bogus) extra flags  (Is this okay?)
  $head .= "\0";		// (bogus) os type (Is this okay?)

  $tail = zip_pack($crc32, 4);
  $tail .= zip_pack($uncomp_size, 4);

  return gzuncompress($head . $data . $tail);
}

function unixtime2dostime ($unix_time) {
  if ($unix_time % 1)
      $unix_time++;		// Round up to even seconds.

  list ($year,$month,$mday,$hour,$min,$sec)
      = explode(" ", date("Y n j G i s", $unix_time));

  if ($year < 1980)
      list ($year,$month,$mday,$hour,$min,$sec) = array(1980, 1, 1, 0, 0, 0);
    
  $dosdate = (($year - 1980) << 9) | ($month << 5) | $mday;
  $dostime = ($hour << 11) | ($min << 5) | ($sec >> 1);

  return array($dosdate, $dostime);
}

/**
 * Class for zipfile creation.
 */
class ZipWriter
{
  function ZipWriter ($comment = "", $zipname = "archive.zip") {
    $this->comment = $comment;
    $this->nfiles = 0;
    $this->dir = "";		// "Central directory block"
    $this->offset = 0;		// Current file position.

    $zipname = addslashes($zipname);
    header("Content-Type: application/zip; name=\"$zipname\"");
    header("Content-Disposition: save; filename=\"$zipname\"");
  }

  function addRegularFile ($filename, $content, $attrib = false) {
    if (!$attrib)
	$attrib = array();

    $size = strlen($content);
    if (function_exists('gzopen'))
      {
	list ($data, $crc32, $comptype, $os_type) = zip_deflate($content);
	if (strlen($data) < $size)
	    $content = $data;	// Use compressed data.
	else
	    unset($crc32);	// force plain store.
      }
    if (!isset($crc32))
      {
	$comptype = 0;
	$crc32 = zip_crc32($content);
      }
    
    if ($attrib['write_protected'])
	$atx = (0100444 << 16) | 1; // S_IFREG + read permissions to everybody.
    else
	$atx = (0100644 << 16); // Add owner write perms.

    $ati = $attrib['is_ascii'] ? 1 : 0;

    if (!$attrib['mtime'])
	$attrib['mtime'] = time();
    list ($mod_date, $mod_time) = unixtime2dostime($attrib['mtime']);

    // Construct parts common to "Local file header" and "Central
    // directory file header."
    
    $head = zip_pack(20, 2); // Version needed to extract (FIXME: is this right?)
    $head .= "\0\0"; //zip_pack(0, 2); // Gen purp bit flag
    $head .= zip_pack($comptype, 2);	// Compression type (DEFLATE)
    $head .= zip_pack($mod_time, 2);
    $head .= zip_pack($mod_date, 2);
    $head .= zip_pack($crc32, 4);
    $head .= zip_pack(strlen($content), 4);
    $head .= zip_pack($size, 4);
    $head .= zip_pack(strlen($filename), 2);
    $head .= zip_pack(strlen($attrib['extra_field']), 2);

    // Construct the "Local file header"
    $lheader = "PK\003\004" . $head . $filename . $attrib['extra_field'];

    // Construct the "central directory file header"
    $this->dir .= "PK\001\002";	// Magic
    // (FIXME: is this right?)   
    $this->dir .= zip_pack(($os_type << 8) + 23, 2); // Version made by
    $this->dir .= $head;
    $this->dir .= zip_pack(strlen($attrib['file_comment']), 2);
    $this->dir .= "\0\0";//zip_pack(0, 2);	// Disk number start
    $this->dir .= zip_pack($ati, 2);// Internal file attributes
    $this->dir .= zip_pack($atx, 4);	// External file attributes
    $this->dir .= zip_pack($this->offset, 4);	// Relative offset of local header
    $this->dir .= $filename . $attrib['extra_field'] . $attrib['file_comment'];

    // Output the "Local file header" and file contents.
    echo $lheader;
    echo $content;

    $this->offset += strlen($lheader) + strlen($content);
    $this->nfiles++;
  }

  function finish () {
    // Output the central directory
    echo $this->dir;

    // Construct the "End of central directory record"
    echo "PK\005\006";
    echo zip_pack(0, 2);	// Number of this disk.
    echo zip_pack(0, 2);	// Number of this disk with start of c dir
    echo zip_pack($this->nfiles, 2);	// Number entries on this disk
    echo zip_pack($this->nfiles, 2);	// Number entries
    echo zip_pack(strlen($this->dir), 4);	// Size of central directory
    echo zip_pack($this->offset, 4);	// Offset of central directory
    echo zip_pack(strlen($this->comment), 2);// Offset of central directory
    echo $this->comment;
  }
}


/**
 * Class for reading zip files.
 *
 * BUGS:
 *
 * Many of the die()'s should probably be warn()'s (eg. CRC mismatch).
 *
 * Only a subset of zip formats is recognized.  (I think that unsupported
 * formats will be recognized as such rather than silently munged.)
 *
 * We don't read the central directory.  This means we don't see the
 * file attributes (text? read-only?), or file comments.
 *
 * Right now we ignore the file mod date and time, since we don't need it.
 */
class ZipReader
{
  function ZipReader ($zipfile) {
    if (!($this->fp = fopen($zipfile, "rb")))
	die("Can't open zip file '$zipfile' for reading");
  }

  function _read ($nbytes) {
    $chunk = fread($this->fp, $nbytes);
    if (strlen($chunk) != $nbytes)
	die("Unexpected EOF in zip file");
    return $chunk;
  }

  function done () {
    fclose($this->fp);
    return false;
  }
  
  function readFile () {
    $head = $this->_read(30);

    $magic = substr($head,0,4);
    //FIXME: we should probably check this:
    //$req_version = zip_unpack($head,4,2);
    $flags = zip_unpack($head,6,2);
    $comp_type = zip_unpack($head, 8, 2);
    //$mod_time = zip_unpack($head, 10, 2);
    //$mod_date = zip_unpack($head, 12, 2);
    $crc32 = zip_unpack($head, 14, 4);
    $comp_size = zip_unpack($head, 18, 4);
    $uncomp_size = zip_unpack($head, 22, 4);
    $filename_len = zip_unpack($head, 26, 2);
    $extrafld_len = zip_unpack($head, 28, 2);
    
    if ($magic != "PK\003\004")
      {
	if ($magic != "PK\001\002") // "Central directory header"
	    die("Bad header type: " . htmlspecialchars($magic));
	return $this->done();
      }
    if (($flags & 0x21) != 0)
	die("Encryption and/or zip patches not supported.");
    if (($flags & 0x08) != 0)
	die("Postponed CRC not yet supported."); // FIXME: ???

    $filename = $this->_read($filename_len);
    if ($extrafld_len != 0)
	$attrib['extra_field'] = $this->_read($extrafld_len);

    $data = $this->_read($comp_size);

    if ($comp_type == 8)
      {
	if (!function_exists('gzopen'))
	    die("Can't inflate data: zlib support not enabled in this PHP");
	$data = zip_inflate($data, $crc32, $uncomp_size, $comp_type);
      }
    else if ($comp_type == 0)
      {
	$crc = zip_crc32($data);
	if ($crc32 != $crc)
	    die(sprintf("CRC mismatch %x != %x", $crc, $crc32));
      }
    else
	die("Compression method $comp_method unsupported");

    if (strlen($data) != $uncomp_size)
	die(sprintf("Uncompressed size mismatch %d != %d",
		    strlen($data), $uncomp_size));

    return array($filename, $data, $attrib);
  }
}

/**
 * Routines for Mime mailification of pages.
 */
//FIXME: these should go elsewhere (wiki_stdlib).
function ctime ($unix_time)
{
  return date("D M j H:i:s Y", $unix_time);
}

function rfc1123date ($unix_time)
{
  $zone = ' +';
  
  $zonesecs = date("Z", $unix_time);
  if ($zonesecs < 0)
      $zone = ' -';

  $zonemins = (int)((abs($zonesecs) + 30) / 60);
  $zonehrs = (int)(($zonemins + 30) / 60);
  $zonemins -= $zonehrs * 60;
  $zone .= sprintf("%02d%02d", $zonehrs, $zonemins);

  return date("D, j M Y H:i:s", $unix_time) . $zone;
}

/**
 * Routines for quoted-printable en/decoding.
 */
function QuotedPrintableEncode ($lines)
{
  $lines = preg_replace('/\r?\n/', '', $lines);
  reset ($lines);
  while (list($junk, $line) = each($lines))
    {
      // Quote special characters in line.
      $quoted = "";
      while ($line)
	{
	  preg_match('/^([ !-<>-~]*)(?:([!-<>-~]$)|(.))/s', $line, $match);
	  $quoted .= $match[1] . $match[2];
	  if ($match[3])
	      $quoted .= sprintf("=%02X", ord($match[3]));
	  $line = substr($line, strlen($match[0]));
	}

      // Split line.
      $out .= preg_replace('/(?=.{77})(.{10,75}[ \t]|.{72,74}[^=][^=])/s',
			   "\\1=\r\n", $quoted) . "\r\n";
    }
  return $out;
}

function QuotedPrintableDecode ($string)
{
  // Eliminate soft line-breaks.
  $string = preg_replace('/=\s*\r?\n/', '', $string);
  // Unquote quoted chars.
  while (preg_match('/^(.*?)=([0-9a-fA-F]{2})/s', $string, $match))
    {
      $unquoted .= $match[1] . chr(hexdec($match[2]));
      $string = substr($string, strlen($match[0]));
    }
  return preg_split('/\r?\n/',
		    preg_replace('/\r?\n$/', '', $unquoted . $string));
}


define('MIME_TOKEN_REGEXP', "[-!#-'*+.0-9A-Z^-~]+");

function MimeContentTypeHeader ($type, $subtype, $params)
{
  $header = "Content-Type: $type/$subtype";
  reset($params);
  while (list($key, $val) = each($params))
    {
      //FIXME:  what about non-ascii printables in $val?
      if (!preg_match('/^' . MIME_TOKEN_REGEXP . '$/', $val))
	  $val = '"' . addslashes($val) . '"';
      $header .= ";\r\n  $key=$val";
    }
  return "$header\r\n";
}

function MimeMultipart ($parts) 
{
  global $mime_multipart_count;

  // The string "=_" can not occur in quoted-printable encoded data.
  $boundary = "=_multipart_boundary_" . ++$mime_multipart_count;
  
  $head = MimeContentTypeHeader('multipart', 'mixed',
				array('boundary' => $boundary));

  $sep = "\r\n--$boundary\r\n";

  return $head . $sep . implode($sep, $parts) . "\r\n--${boundary}--\r\n";
}
  
function MimeifyPage ($pagehash) {
  extract($pagehash);

  $params = array('pagename' => rawurlencode($pagename),
		  'author' => rawurlencode($author),
		  'version' => $version,
		  'flags' =>"",
		  'lastmodified' => $lastmodified,
		  'created' => $created);

  if (($flags & FLAG_PAGE_LOCKED) != 0)
      $params['flags'] = 'PAGE_LOCKED';
  for ($i = 1; $i <= NUM_LINKS; $i++) 
      if ($ref = $refs[$i])
	  $params["ref$i"] = rawurlencode($ref);
  
  $head = MimeContentTypeHeader('application', 'x-phpwiki', $params);
  $head .= "Content-Transfer-Encoding: quoted-printable\r\n";
  
  return "$head\r\n" . QuotedPrintableEncode($content);
}

function MimeifyPages ($pagehashes)
{
  $npages = sizeof($pagehashes);
  for ($i = 0; $i < $npages; $i++)
      $parts[$i] = MimeifyPage($pagehashes[$i]);
  return $npages == 1 ? $parts[0] : MimeMultipart($parts);
}


/**
 * Routines for parsing Mime-ified phpwiki pages.
 */
function ParseRFC822Headers (&$string)
{
  if (preg_match("/^From (.*)\r?\n/", $string, $match))
    {
      $headers['from '] = preg_replace('/^\s+|\s+$/', '', $match[1]);
      $string = substr($string, strlen($match[0]));
    }

  while (preg_match('/^([!-9;-~]+) [ \t]* : [ \t]* '
		    . '( .* \r?\n (?: [ \t] .* \r?\n)* )/x',
		    $string, $match))
    {
      $headers[strtolower($match[1])]
	   = preg_replace('/^\s+|\s+$/', '', $match[2]);
      $string = substr($string, strlen($match[0]));
    }

  if (!$headers)
      return false;

  if (! preg_match("/^\r?\n/", $string, $match))
      die("No blank line after headers:\n  '"
	  . htmlspecialchars($string) . "'");
  $string = substr($string, strlen($match[0]));
  
  return $headers;
}


function ParseMimeContentType ($string)
{
  // FIXME: Remove (RFC822 style comments).

  // Get type/subtype
  if (!preg_match(':^\s*(' . MIME_TOKEN_REGEXP . ')\s*'
		  . '/'
		  . '\s*(' . MIME_TOKEN_REGEXP . ')\s*:x',
		  $string, $match))
      die ("Bad MIME content-type");

  $type = strtolower($match[1]);
  $subtype = strtolower($match[2]);
  $string = substr($string, strlen($match[0]));
  
  $param = array();

  while (preg_match('/^;\s*(' . MIME_TOKEN_REGEXP . ')\s*=\s*'
		    . '(?:(' . MIME_TOKEN_REGEXP . ')|"((?:[^"\\\\]|\\.)*)") \s*/sx',
		    $string, $match))
    {
      if ($match[2])
	  $val = $match[2];
      else
	  $val = preg_replace('/[\\\\](.)/s', '\\1', $match[3]);
      
      $param[strtolower($match[1])] = $val;
	   
      $string = substr($string, strlen($match[0]));
    }

  return array($type, $subtype, $param);
}

function ParseMimeMultipart($data, $boundary)
{
  if (!$boundary)
      die("No boundary?");

  $boundary = preg_quote($boundary);

  while (preg_match("/^(|.*?\n)--$boundary((?:--)?)[^\n]*\n/s",
		     $data, $match))
    {
      $data = substr($data, strlen($match[0]));
      if ( ! isset($parts) )
	  $parts = array();	// First time through: discard leading chaff
      else
	{
	  if ($content = ParseMimeifiedPages($match[1]))
	      for (reset($content); $p = current($content); next($content))
		  $parts[] = $p;
	}

      if ($match[2])
	  return $parts;	// End boundary found.
    }
  die("No end boundary?");
}

function ParseMimeifiedPages ($data)
{
  if (!($headers = ParseRFC822Headers($data))
      || !($typeheader = $headers['content-type']))
    {
      //warn("Can't find content-type header");
      return false;
    }
  
  if (!(list ($type, $subtype, $params) = ParseMimeContentType($typeheader)))
    {
      warn("Can't parse content-type: ("
	   . htmlspecialchars($typeheader) . ")");
      return false;
    }
  if ("$type/$subtype" == 'multipart/mixed')
      return ParseMimeMultipart($data, $params['boundary']);
  else if ("$type/$subtype" != 'application/x-phpwiki')
    {
      warn("Bad content-type: $type/$subtype");
      return false;
    }

  // FIXME: more sanity checking?
  $pagehash = array('pagename' => rawurldecode($params['pagename']),
		    'author' => rawurldecode($params['author']),
		    'version' => $params['version'],
		    'lastmodified' => $params['lastmodified'],
		    'created' => $params['created']);
  $pagehash['flags'] = 0;
  if (preg_match('/PAGE_LOCKED/', $params['flags']))
      $pagehash['flags'] |= FLAG_PAGE_LOCKED;
  for ($i = 1; $i <= NUM_LINKS; $i++) 
      if ($ref = $params["ref$i"])
	  $pagehash['refs'][$i] = rawurldecode($ref);


  $encoding = strtolower($headers['content-transfer-encoding']);
  if (!$encoding || $encoding == 'binary')
      $pagehash['content'] = preg_split('/\r?\n/', $data);
  else if ($encoding == 'quoted-printable')
      $pagehash['content'] = QuotedPrintableDecode($data);
  else
      die("Unknown encoding type: $enconding");
  

  return array($pagehash);
}

?>
