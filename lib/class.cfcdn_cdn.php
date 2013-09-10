<?php
/**
 * Connection layer to CDN.
 */
class CFCDN_CDN{

  public $api_settings;
  public $uploads;
  public $cache_file;
  public $cache_folder;

  function __construct() {
    $this->api_settings = $this->settings();
    $this->uploads = wp_upload_dir();
    $this->cache_folder = $this->uploads['basedir'] . "/cdn/tmp/";
    $this->cache_file = $this->cache_folder . "cache.csv";
  }
  
 /**
  * CloudFiles CDN Settings.
  */
  public function settings(){
    $default_settings = array( 'username' => 'YOUR USERNAME',
                               'apiKey' => 'YOUR API KEY',
                               'container' => 'YOUR CONTAINER',
                               'public_url' => 'http://YOUR LONG URL.rackcdn.com',
                               'url' => 'https://identity.api.rackspacecloud.com/v2.0/',
                               'serviceName' => 'cloudFiles',
                               'region' => 'DFW',
                               'urltype' => 'publicURL' );
  
    return get_option( CFCDN_OPTIONS, $default_settings );
  }
  
  
 /**
  *  Openstack Connection Object.
  */
  function connection_object(){
  
    $api_settings = $this->api_settings;
    $connection = new \OpenCloud\Rackspace(
                            $api_settings['url'],
                            array(  'username' => $api_settings['username'],
                                    'apiKey' => $api_settings['apiKey']  ) );
  
    $cdn = $connection->ObjectStore( $api_settings['serviceName'], $api_settings['region'], $api_settings['urltype'] );
    return $cdn;
  }
  
  
 /**
  *  Openstack CDN Container Object.
  */
  public function container_object(){
    $api_settings = $this->api_settings;
    $cdn = $this->connection_object();
    $container = $cdn->Container($api_settings['container']);
    return $container;
  }
  

 /**
  * Puts given file attachment onto CDN.
  */
  public function upload_file( $file_path ){
    
    $relative_file_path = str_replace( $this->uploads['basedir'] . "/", '',  $file_path );
    $container = $this->container_object();
    $file = $container->DataObject();
    $file->SetData( file_get_contents( $file_path ) );
    $file->name = $relative_file_path;
    $file->Create();

  }


 /**
  * List of files uploaded to CDN as recorded in cache file.
  */
  public function get_uploaded_files(){
  
    if( !file_exists( $this->cache_file ) ){
      mkdir( $this->cache_folder, 0777, true );
    }

    $fp = fopen( $this->cache_file, 'w' ) or die('Cannot open file:  ' . $this->cache_file );
    $files = array_diff( str_getcsv( $this->cache_file ), array(".", "..", $this->cache_file) );
    fclose( $fp );

    return $files;
  }

  
}
?>
