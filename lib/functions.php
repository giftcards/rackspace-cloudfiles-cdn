<?php

/**
 * Rewrite asset URLs on the fly to pull from CDN.
 *
 * @TODO: Find a way to allow some images to be served with WordPress and new ones to be served with CloudFiles
 */
function cfcdn_rewrite_on_fly($content)
{
    $uploads = wp_upload_dir();
    $uploads_url = str_replace(array('http://', 'https://'), '', $uploads['baseurl']);

    $cdn_settings = CFCDN_CDN::settings();
    $public_url = str_replace(array('http://', 'https://'), '', $cdn_settings['public_url']);

    return str_replace($uploads_url, $public_url, $content);

}

add_filter("the_content", "cfcdn_rewrite_on_fly");


/**
 * Rewrite attachment URL to pull from CDN.
 */
function cfcdn_rewrite_attachment_url($url)
{
    $uploads = wp_upload_dir();
    $assetUrl = str_replace($uploads['baseurl'], '', $url);
    $cdn = new CFCDN_CDN();
    if ($cdn->is_cached($uploads['basedir'] . '/' . $assetUrl)) {
        $uploads_url = str_replace(array('http://', 'https://'), '', $uploads['baseurl']);

        $cdn_settings = CFCDN_CDN::settings();
        $public_url = str_replace(array('http://', 'https://'), '', $cdn_settings['public_url']);

        return str_replace($uploads_url, $public_url, $url);
    } else {
        return $url;
    }

}

add_filter('wp_get_attachment_url', 'cfcdn_rewrite_attachment_url');


/**
 * Save file to cloudfiles when uploading new attachment.
 */
function cfcdn_send_to_cdn_on_attachment_post_save($post_id)
{
    if (is_numeric($post_id)) {
        $uploadInfo = wp_upload_dir();

        // Single image, no metadata created
        $image_path = get_post_meta($post_id, '_wp_attached_file', true);
        $file_name = $uploadInfo['basedir'] . '/' . $image_path;

        CFCDN_Util::upload_single_file($file_name);
    }
}

add_action('add_attachment', 'cfcdn_send_to_cdn_on_attachment_post_save');


function cfcdn_send_to_cdn_on_metadata_post_save($notUsed, $id)
{
    $cdn = new CFCDN_CDN();

    $file = get_attached_file($id);
    $meta = wp_get_attachment_metadata($id);
    $filePath = str_replace(wp_basename($file), "", $file);

    // Check to make sure the base file was actually tracked to CloudFiles
    if ($cdn->is_cached($file)) {
        if (is_array($meta['sizes'])) {
            foreach ($meta['sizes'] as $file) {
                $file_name = $filePath . $file['file'];

                if (file_exists($file_name)) {
                    CFCDN_Util::upload_single_file($file_name);
                }
            }
        }
    }

    // Return null, so WordPress keeps processing.
    return null;
}

//add_action('wp_update_attachment_metadata', 'cfcdn_send_to_cdn_on_metadata_post_save', 1000);
add_action('image_downsize', 'cfcdn_send_to_cdn_on_metadata_post_save', 100, 2);

/**
 * Make sure all files are pushed to CDN on admin page load after initial push.
 */
function cfcdn_admin_page_load()
{
    if (is_admin()) {
        CFCDN_Util::upload_all();
    }
}

add_action('shutdown', 'cfcdn_admin_page_load');