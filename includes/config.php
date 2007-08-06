<?php
/* This file loads configuration settings from the data file settings.php and
 * sets up some needed variables.
 *
 * The settings.php file is created during installation using the web-based db
 * setup page (install/index.php).
 *
 * <b>Note:</b>
 * DO NOT EDIT THIS FILE!
 *
 *
 * @author Craig Knudsen <cknudsen@cknudsen.com>
 * @copyright Craig Knudsen, <cknudsen@cknudsen.com>, http://www.k5n.us/cknudsen
 * @license http://www.gnu.org/licenses/gpl.html GNU GPL
 * @version $Id$
 * @package WebCalendar
 */

  // We can add extra 'nonuser' calendars such as a holiday, corporate,
  // departmental, etc.  We need a unique prefix for these calendars
  // so we don't get them mixed up with real logins.  This prefix should be
  // a maximum of 5 characters and should NOT change once set!
  define ( '_WC_NONUSER_PREFIX', '_NUC_' );
  define ( 'PROGRAM_VERSION', 'v2.0' );
  define ( 'PROGRAM_DATE', '?? ??? 2007' );
  define ( 'PROGRAM_NAME', 'WebCalendar ' . 
    PROGRAM_VERSION .'( '. PROGRAM_DATE .' )' );
  define ( 'PROGRAM_URL', 'http://www.k5n.us/webcalendar.php' );
  define ( 'TROUBLE_URL', 'docs/WebCalendar-SysAdmin.html#trouble' );
   
   // Note:  When running from the command line (send_reminders.php),
  // these variables are (obviously) not set.
  if ( isset ( $_SERVER ) && is_array ( $_SERVER ) ) {
    if ( empty ( $HTTP_HOST ) && isset ( $_SERVER['HTTP_HOST'] ) )
      $HTTP_HOST = $_SERVER['HTTP_HOST'];
    if ( empty ( $SERVER_PORT ) && isset ( $_SERVER['SERVER_PORT'] ) )
      $SERVER_PORT = $_SERVER['SERVER_PORT'];
    if ( ! isset ( $_SERVER['REQUEST_URI'] ) ) {
      $arr = explode ( '/', $_SERVER['PHP_SELF'] );
      $_SERVER['REQUEST_URI'] = '/' . $arr[count ( $arr )-1];
      if ( isset ( $_SERVER['argv'][0] ) && $_SERVER['argv'][0] != '' )
        $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['argv'][0];
    }
    if ( empty ( $REQUEST_URI ) && isset ( $_SERVER['REQUEST_URI'] ) )
      $REQUEST_URI = $_SERVER['REQUEST_URI'];
    // Hack to fix up IIS.
    if ( isset ( $_SERVER['SERVER_SOFTWARE'] ) &&
        strstr ( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) &&
        isset ( $_SERVER['SCRIPT_NAME'] ) )
      $REQUEST_URI = $_SERVER['SCRIPT_NAME'];
  }
   
/* Prints a fatal error message to the user along with a link to the
 * Troubleshooting section of the WebCalendar System Administrator's Guide.
 *
 * Execution is aborted.
 *
 * @param string  $error  The error message to display
 * @internal We don't normally put functions in this file. But, since this
 *           file is included before some of the others, this function either
 *           goes here or we repeat this code in multiple files.
 */
function die_miserable_death ( $error ) {

  $db_valid = defined ( '_WC_DB_TYPE' );
  $trouble_url = TROUBLE_URL;
  // Make sure app name is set.
  $appStr = ( $db_valid &&  function_exists ( 'generate_application_name' )
    ? generate_application_name () : 'Title' );

  if ( $db_valid && function_exists ( 'translate' ) ) {
    $h2_label = $appStr . ' ' . translate ( 'Error' );
    $title = $appStr . ': ' . translate ( 'Fatal Error' );
    $trouble_label = translate ( 'Troubleshooting Help' );
    $user_BGCOLOR = getPref ( 'BGCOLOR' );
  } else {
    $appStr = 'WebCalendar';
    $h2_label = $appStr . ' ' . 'Error';
    $title = $appStr . ': ' . 'Fatal Error';
    $trouble_label = 'Troubleshooting Help';
    $user_BGCOLOR = '#FFFFFF';
  }

  echo <<<EOT
<html>
  <head><title>{$title}</title></head>
  <body bgcolor ="{$user_BGCOLOR}">
    <h2>{$h2_label}</h2>
    <p>{$error}</p><hr />
    <p><a href="{$trouble_url}" target="_blank">{$trouble_label}</a></p>
  </body>
</html>
EOT;
  exit;
}

function db_error ( $doExit = false, $sql = '' ) {

  $ret = str_replace ( 'XXX', dbi_error (), translate ( 'Database error XXX.' ) )
   . ( _WC_RUN_MODE == 'dev' && ! empty ( $sql ) ? '<br />SQL:<br />' . $sql : '' );

  if ( $doExit ) {
    echo $ret;
    exit;
  } else
    return $ret;
}

function do_config ( $fileLoc ) {
   $fd = '';
   if ( ! file_exists ( $fileLoc  ) ) 
     $fileLoc  = dirname ( __FILE__ ) .'/settings.php';
  // Open settings file to read.
  $cfg = array ();
  if ( file_exists ( $fileLoc  ) )
    $fd = @fopen ( $fileLoc, 'rb', true );
  if ( empty ( $fd ) ) {
    // Redirect user to install page if it exists.
    if ( file_exists ( 'install/index.php' ) ) {
      die_via_installation ();
    } else  // There is no settings.php file.
	  die_miserable_death ( str_replace ( 'XXX defined in ', '',
        translate ( 'Could not find XXX defined in settings.php file...' ) ) );
  }
  $data = '';
  while ( ! feof ( $fd ) ) {
    $data .= fgets ( $fd, 4096 );
  }
  fclose ( $fd );
  
  if ( empty ( $data ) ) //we may have just created an empty file
    die_via_installation ();
  // Replace any combination of carriage return (\r) and new line (\n)
  // with a single new line.
  $data = preg_replace ( "/[\r\n]+/", "\n", $data );

  // Split the data into lines.
  $configLines = explode ( "\n", $data );

  for ( $n = 0, $cnt = count ( $configLines ); $n < $cnt; $n++ ) {
    $buffer = trim ( $configLines[$n], "\r\n " );
    if ( preg_match ( '/^#|\/\*/', $buffer ) || // comments
        preg_match ( '/^<\?/', $buffer ) || // start PHP code
        preg_match ( '/^\?>/', $buffer ) ) // end PHP code
      continue;
    if ( preg_match ( '/(\S+):\s*(\S+)/', $buffer, $matches ) )
      $cfg[$matches[1]] = $matches[2];
    // echo "settings $matches[1] => $matches[2]<br />";
  }
  $configLines = $data = '';

  foreach ( array ( 'db_type', 'db_host', 'db_login', 'db_password' ) as $s ) {
    if ( empty ( $cfg[$s] ) )
      die_miserable_death ( str_replace ( 'XXX', $s,
          translate ( 'Could not find XXX defined in settings.php file...' ) ) );
  }

  // Allow special settings of 'none' in some settings[] values.
  // This can be used for db servers not using TCP port for connection.
  $cfg['db_host'] = ( $cfg['db_host'] == 'none' ? '' : $cfg['db_host'] );
  $cfg['db_password'] = ( $cfg['db_password'] == 'none' ? '' : $cfg['db_password'] );
  
  // Extract db settings into defines
  define ( '_WC_INSTALL_PASSWORD', $cfg['install_password'] );
  define ( '_WC_DB_DATABASE', $cfg['db_database'] );
  define ( '_WC_DB_HOST', $cfg['db_host'] );
  define ( '_WC_DB_LOGIN', $cfg['db_login'] );
  define ( '_WC_DB_PASSWORD', $cfg['db_password'] );
  define ( '_WC_DB_PERSISTENT', ( preg_match ( '/(1|yes|true|on)/i',
      $cfg['db_persistent'] ) ? true : false ) );
  define ( '_WC_DB_TYPE', $cfg['db_type'] );
  define ( '_WC_DB_PREFIX', $cfg['db_prefix'] );

  // Use 'db_cachedir' if found, otherwise look for 'cachedir'.
  if ( ! empty ( $cfg['db_cachedir'] ) && @is_dir ( $cfg['db_cachedir'] ) )
    define ( '_WC_DB_CACHEDIR', $cfg['db_cachedir'] );
  else if ( ! empty ( $cfg['cachedir'] ) && @is_dir ( $cfg['cachedir'] ) )
     define ( '_WC_DB_CACHEDIR', $cfg['cachedir'] );

  if ( defined ( '_WC_DB_CACHEDIR' ) ) 
    dbi_init_cache ( _WC_DB_CACHEDIR );
	
  if ( ! empty ( $cfg['db_debug'] ) &&
      preg_match ( '/(1|true|yes|enable|on)/i', $cfg['db_debug'] ) )
    dbi_set_debug ( true );

  define ( '_WC_READ_ONLY_PROTO',  preg_match ( '/(1|yes|true|on)/i',
    $cfg['readonly'] ) ? true : false );

  if ( empty ( $cfg['mode'] ) )
    $cfg['mode'] = 'prod';

  define ( '_WC_RUN_MODE', preg_match ( '/(dev)/i', 
    $cfg['mode'] ) ? 'dev' : 'prod' );
   
  define ( '_WC_phpdbiVerbose',  _WC_RUN_MODE == 'dev' ? true : false );
  
  define ( '_WC_SINGLE_USER', preg_match ( '/(1|yes|true|on)/i',
    $cfg['single_user'] ) ? true : false );

  define ( '_WC_SINGLE_USER_LOGIN', ! empty ( $cfg['single_user_login'] ) 
	  ? $cfg['single_user_login'] : '' );
  if ( _WC_SINGLE_USER && ! _WC_SINGLE_USER_LOGIN  )
    die_miserable_death ( str_replace ( 'XXX', 'single_user_login',
        translate ( 'You must define XXX in' ) ) );

  define ( '_WC_HTTP_AUTH', preg_match ( '/(1|yes|true|on)/i',
      $cfg['use_http_auth'] ) ? true : false );

  // Type of user authentication.
  define( '_WC_USER_INC', 'classes/user/' . $cfg['user_inc']. '.class.php' );

  // Check the current installation version.
  // Redirect user to install page if it is different from stored value.
  // This will prevent running WebCalendar until UPGRADING.html has been
  // read and required upgrade actions completed.
  $c = @dbi_connect ( _WC_DB_HOST, _WC_DB_LOGIN, 
    _WC_DB_PASSWORD, _WC_DB_DATABASE, false );
  if ( $c ) {
    $rows = @dbi_execute ( 'SELECT cal_value FROM webcal_config
       WHERE cal_setting = \'WEBCAL_PROGRAM_VERSION\'',array(), false, false );
    if ( $rows ) {
      $row = $rows[0];
      if ( ! empty ( $row ) && $row[0] != PROGRAM_VERSION ) {
        die_via_installation ( $row[0] );
      }
    } else
	  die_via_installation ();
    dbi_close ( $c );
  } else { // Must mean we don't have a settings.php file.
    die_via_installation ();
  }
}

function die_via_installation ( $ver='UNKNOWN' ) {
  // &amp; does not work here...leave it as &.
  header ( 'Location: install/index.php?action=mismatch&version=' . $ver );
  exit;
}
?>
