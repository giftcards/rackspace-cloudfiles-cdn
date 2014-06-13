<?php

/**
 * Connection layer to CDN.
 */
class CFCDN_CDN
{

    public $api_settings;
    public $uploads;
    public $cache_file;
    public $cache_folder;

    function __construct()
    {
        $this->api_settings = $this->settings();
        $this->uploads = wp_upload_dir();
        $this->cache_folder = $this->uploads['basedir'] . "/cdn/tmp/";
        $this->cache_file = $this->cache_folder . "cache.csv";
    }

    /**
     * CloudFiles CDN Settings.
     */
    public static function settings()
    {
        $default_settings = array(
            'username' => 'YOUR USERNAME',
            'apiKey' => 'YOUR API KEY',
            'container' => 'YOUR CONTAINER',
            'public_url' => 'http://YOUR LONG URL.rackcdn.com',
            'region' => 'DFW',
            'url' => 'https://identity.api.rackspacecloud.com/v2.0/',
            'serviceName' => 'cloudFiles',
            'urltype' => 'publicURL',
            'first_upload' => 'false',
            'delete_local_files' => 'true'
        );

        return get_option(CFCDN_OPTIONS, $default_settings);
    }


    /**
     *  Openstack Connection Object.
     */
    function connection_object()
    {

        $api_settings = $this->api_settings;
        $connection = new \OpenCloud\Rackspace(
            $api_settings['url'],
            array(
                'username' => $api_settings['username'],
                'apiKey' => $api_settings['apiKey']
            ));

        $cdn = $connection->ObjectStore(
            $api_settings['serviceName'],
            $api_settings['region'],
            $api_settings['urltype']
        );
        return $cdn;
    }


    /**
     *  Openstack CDN Container Object.
     */
    public function container_object()
    {
        $api_settings = $this->api_settings;
        $cdn = $this->connection_object();
        $container = $cdn->Container($api_settings['container']);
        return $container;
    }


    /**
     * Puts given file attachment onto CDN.
     */
    public function upload_file($file_path)
    {
        if (!$this->is_cached($file_path)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $contentType = finfo_file($finfo, $file_path);

            $relative_file_path = str_replace($this->uploads['basedir'] . "/", '', $file_path);
            $container = $this->container_object();
            $file = $container->DataObject();
            $file->SetData(file_get_contents($file_path));
            $file->name = $relative_file_path;
            $file->Create(array('content_type' => $contentType));

            $this->write_to_cache($file_path);
        }
    }


    /**
     * List of files uploaded to CDN as recorded in cache file.
     */
    public function get_uploaded_files()
    {

        if (!file_exists($this->cache_file)) {
            mkdir($this->cache_folder, 0777, true);
            $fp = fopen($this->cache_file, 'ab') or die('Cannot open file:  ' . $this->cache_file);
            fclose($fp);
        }

        $fp = fopen($this->cache_file, 'rb') or die('Cannot open file:  ' . $this->cache_file);
        $lines = array_map("rtrim", file($this->cache_file));
        $files = array_diff($lines, array(".", "..", $this->cache_file));
        fclose($fp);

        return $files;
    }


    /**
     * Write file path the cache file once file is uploaded to CDN.
     */
    public function write_to_cache($file_path)
    {
        global $wpdb;

        if (!$this->is_cached($file_path)) {
            $wpdb->query("insert into " . $wpdb->prefix . "cfcdn (filename) VALUES ('" . $file_path . "')");
        }
    }

    public function is_cached($file_path)
    {
        global $wpdb;

        $row = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "cfcdn WHERE filename='" . $file_path . "'");

        return ($row === null) ? false : true;
    }


    /**
     * Change setting via key value pair.
     */
    public function update_setting($setting, $value)
    {
        if (current_user_can('manage_options') && !empty($setting)) {
            $api_settings = $this->api_settings;
            $api_settings[$setting] = $value;
            update_option(CFCDN_OPTIONS, $api_settings);
            $this->api_settings = $api_settings;
        }

    }

}