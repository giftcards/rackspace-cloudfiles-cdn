<?php

/**
 * Abstraction layer over WordPress attachments for getting and
 * pushing attachments to and from CDN.
 */
class CFCDN_Attachments
{

    public $uploads;
    public $cache_file;

    public $local_files;
    public $uploaded_files;
    public $file_needing_upload;


    function __construct()
    {
        $this->uploads = wp_upload_dir();
        $this->load_cache();
        $this->load_local_files();
    }


    /**
     * Finds local attachment files and uploads them to CDN. Will not upload all until
     * after first manual upload to CDN.
     * Requires PHP Directory Iterator installed on server.
     * Included in Standard PHP Library (SPL) - http://php.net/manual/en/book.spl.php
     *
     * @see http://de.php.net/manual/en/directoryiterator.construct.php
     */
    public function upload_all()
    {

        $cdn = new CFCDN_CDN();

        if (!empty($cdn->api_settings['first_upload']) && $cdn->api_settings['first_upload'] == "true") {
            $this->load_files_needing_upload();

            foreach ($this->files_needing_upload as $file_path) {
                $cdn->upload_file($file_path);
            }
        }

    }

    /*
     * Upload a single file to CloudFiles
     */
    public function upload_single_file($file_path) {
        $cdn = new CFCDN_CDN();
        $cdn->upload_file($file_path);
    }


    /**
     * Gets all files in the local WP Uploads folder and set into $local_files.
     */
    public function load_local_files()
    {
        $path = $this->uploads['basedir'];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $local_files = array();
        foreach ($files as $name => $file_info) {
            if (substr($name, -1) != "." && substr($name, -1) != ".." && $name != $this->cache_file) {
                $local_files[] = $name;
            }
        }

        $this->local_files = $local_files;
    }


    /**
     * Load lists of files already uploaded to CDN.
     * Uses a persistance file in uploads folder
     */
    public function load_cache()
    {
        $cdn = new CFCDN_CDN();
        $this->cache_file = $cdn->cache_file;
        $this->uploaded_files = $cdn->get_uploaded_files();

    }


    /**
     * Calculate files that need to be uploaded. Not done on class init.
     * Sticks into array $this->files_needing_upload;
     */
    public function load_files_needing_upload()
    {
        $this->files_needing_upload = array_diff($this->local_files, $this->uploaded_files);
    }


    /**
     *  File paths needing upload as JSON.
     */
    public function needing_upload_as_json()
    {
        $this->files_needing_upload = array_diff($this->local_files, $this->uploaded_files);
        return json_encode($this->files_needing_upload);
    }


    /**
     * Delete local copies of files that are already pushed to CDN.
     */
    public function delete_local_files()
    {

        $cdn = new CFCDN_CDN();
        if ($cdn->api_settings['delete_local_files'] == "true" && !empty($this->uploaded_files)) {
            foreach ($this->uploaded_files as $file_path) {
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
    }

}