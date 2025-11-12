<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace repository_cloudpoodll;

defined('MOODLE_INTERNAL') || die();

use repository_cloudpoodll\constants;
use repository_cloudpoodll\utils;

/**
 * Class imagegen
 *
 * @package    repository_cloudpoodll
 * @copyright  2025 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imagegen
{

    protected $conf = false;

    function __construct()
    {
        global $DB;
        $this->conf = get_config(constants::M_SHORTNAME);
    }


    public function make_image_smaller($imagedata)
    {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        if (empty($imagedata)) {
            return $imagedata;
        }

        // Create temporary files for resizing
        $randomid = uniqid();
        $temporiginal = $CFG->tempdir . '/aigen_orig_' . $randomid;
        file_put_contents($temporiginal, $imagedata);

        // Resize to reasonable dimensions
        $resizedimagedata = \resize_image($temporiginal, 500, 500, true);

        if (!$resizedimagedata) {
            // If resizing fails, use the original image data
            $resizedimagedata = $imagedata;
        }

        // Clean up temporary file
        if (file_exists($temporiginal)) {
            unlink($temporiginal);
        }

        return $resizedimagedata;
    }

    /*
     * Generates structured data using the CloudPoodll service.
     *
     * @param string $prompt The prompt to generate data for.
     * @return array|false Returns an array with draft file URL, draft item ID, term ID, and base64 data, or false on failure.
     */
    public function edit_image($prompt, $draftid, $file, $filename)
    {
        global $USER, $DB;
        $params = $this->prepare_edit_image_payload($prompt, $file);
        if ($params) {
            $url = utils::get_cloud_poodll_server() . "/webservice/rest/server.php";
            $resp = utils::curl_fetch($url, $params, true);
            $base64data = $this->process_generate_image_response($resp);
            if ($base64data) {
                // Generate draft file
                $filerecord = $this->base64ToFile($base64data, $draftid, $filename);
                if ($filerecord) {
                    $draftid = $filerecord['itemid'];
                    $draftfileurl = \moodle_url::make_draftfile_url(
                        $draftid,
                        $filerecord['filepath'],
                        $filerecord['filename'],
                        false
                    );
                    return [
                        'drafturl' => $draftfileurl->out(false),
                        'draftitemid' => $draftid,
                        'filename' => $filename,
                        'error' => false,
                    ];
                }
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    /**
     * Generates structured data using the CloudPoodll service.
     *
     * @param string $prompt The prompt to generate data for.
     * @return array|false Returns an array with draft file URL, draft item ID, term ID, and base64 data, or false on failure.
     */
    public function generate_image($prompt, $draftid, $filename)
    {
        global $USER, $DB;
        $params = $this->prepare_generate_image_payload(($prompt));
        if ($params) {
            $url = utils::get_cloud_poodll_server() . "/webservice/rest/server.php";
            $resp = utils::curl_fetch($url, $params);
            $base64data = $this->process_generate_image_response($resp);
            if ($base64data) {
                // Generate draft file
                $filerecord = $this->base64ToFile($base64data, $draftid, $filename);
                if ($filerecord) {
                    $draftid = $filerecord['itemid'];
                    $draftfileurl = \moodle_url::make_draftfile_url(
                        $draftid,
                        $filerecord['filepath'],
                        $filerecord['filename'],
                        false,
                    );
                    return [
                        'drafturl' => $draftfileurl->out(false),
                        'draftitemid' => $draftid,
                        'filename' => $filename,
                        'error' => false,
                    ];
                }
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    public function base64ToFile($base64data, $draftid, $filename)
    {
        global $USER;

        if (empty($base64data)) {
            return false;
        }

        $fs = get_file_storage();

        $filerecord = [
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => $filename,
        ];

        // Create file content
        $filecontent = base64_decode($base64data);
        try {
            // Check if the file already exists
            $existingfile = $fs->get_file_by_hash(sha1($filecontent));
            if ($existingfile) {
                return $filerecord;
            } else {
                $thefile = $fs->create_file_from_string($filerecord, $filecontent);
                if ($thefile) {
                    return $filerecord;
                } else {
                    return false;
                }
            }
        } catch (\moodle_exception $e) {
            return false; // Handle error "gracefully"
        }
    }


    public function prepare_edit_image_payload($prompt, $file, $token = null)
    {
        global $USER;

        if (!empty($this->conf->apiuser) && !empty($this->conf->apisecret)) {
            if (is_null($token)) {
                $token = utils::fetch_token($this->conf->apiuser, $this->conf->apisecret);
            }
            if (empty($token)) {
                return false;
            }
            if (!($file || !$file instanceof \stored_file)) {
                return false;
            }

            // Fetch base64 data from the storedfile
            $filecontent = $file->get_content();
            $base64data = base64_encode($filecontent);
            if (!$base64data) {
                return false;
            }

            $params["wstoken"] = $token;
            $params["wsfunction"] = 'local_cpapi_call_ai';
            $params["moodlewsrestformat"] = 'json';
            $params['appid'] = 'repository_cloudpoodll';
            $params['action'] = 'edit_image';
            $params["subject"] = $base64data;
            $params["prompt"] = $prompt;
            $params["language"] = "en-US";
            $params["region"] = $this->conf->awsregion;
            $params['owner'] = hash('md5', $USER->username);

            return $params;

        } else {
            return false;
        }
    }

    public function prepare_generate_image_payload($prompt, $token = null)
    {
        global $USER;

        if (!empty($this->conf->apiuser) && !empty($this->conf->apisecret)) {
            if (is_null($token)) {
                $token = utils::fetch_token($this->conf->apiuser, $this->conf->apisecret);
            }
            if (empty($token)) {
                return false;
            }

            $params["wstoken"] = $token;
            $params["wsfunction"] = 'local_cpapi_call_ai';
            $params["moodlewsrestformat"] = 'json';
            $params['appid'] = 'repository_cloudpoodll';
            $params['action'] = 'generate_images';
            $params["subject"] = '1';
            $params["prompt"] = $prompt;
            $params["language"] = "en-US";
            $params["region"] = $this->conf->awsregion;
            $params['owner'] = hash('md5', $USER->username);

            return $params;

        } else {
            return false;
        }
    }

    public function process_generate_image_response($resp)
    {
        $respobj = json_decode($resp);
        $ret = new \stdClass();
        if (isset($respobj->returnCode)) {
            $ret->success = $respobj->returnCode == '0' ? true : false;
            $ret->payload = json_decode($respobj->returnMessage);
        } else {
            $ret->success = false;
            $ret->payload = "unknown problem occurred";
        }
        if ($ret && $ret->success) {
            if (isset($ret->payload[0]->url)) {
                $url = $ret->payload[0]->url;
                $rawdata = file_get_contents($url);
                if ($rawdata !== false) {
                    $smallerdata = $this->make_image_smaller($rawdata);
                    $base64data = base64_encode($smallerdata);
                    return $base64data;
                }
            } else if (isset($ret->payload[0]->b64_json)) {
                // If the payload has a base64 encoded image, use that.
                $rawbase64data = $ret->payload[0]->b64_json;
                $rawdata = base64_decode($rawbase64data);
                $smallerdata = $this->make_image_smaller($rawdata);
                $base64data = base64_encode($smallerdata);
                return $base64data;
            }
        }
        return null;
    }

}
