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

/**
 * Admin settings for the Tiny font size plugin.
 *
 * @package     tiny_fontsize
 * @copyright   2025 Mikko Haiku <mikko.haiku@iki.fi>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin = 'tiny_fontsize';

$settings = new admin_settingpage('tiny_fontsize_settings', new lang_string('settings', $plugin));
if ($ADMIN->fulltree) {
    // Lock every setting except the license key itself until the license has been
    // validated successfully. This uses the same mechanism as forcing a setting via
    // config.php, so the field is both rendered disabled and pinned to its current
    // value even if a request bypassing the disabled control tried to change it.
    $licensevalidationdata = get_config('tiny_fontsize', 'license_validation_data');
    $licensevalidationerror = get_config('tiny_fontsize', 'license_validation_error');
    $licensevalid = false;
    if (empty($licensevalidationerror) && !empty($licensevalidationdata)) {
        $decodedlicensedata = json_decode($licensevalidationdata, true);
        $licensevalid = !empty($decodedlicensedata['valid']);
    }

    if (!$licensevalid) {
        foreach (['fontsizes', 'fontsizeunit'] as $lockedsetting) {
            $CFG->forced_plugin_settings['tiny_fontsize'][$lockedsetting] = get_config('tiny_fontsize', $lockedsetting);
        }
    }

    $defaults = [
        '10',
        '12',
        '14',
        '18',
    ];

    $settings->add(
        new admin_setting_configtextarea('tiny_fontsize/fontsizes',
                get_string('fontsizes', 'tiny_fontsize'),
                get_string('fontsizes_desc', 'tiny_fontsize'),
                implode("\r\n", $defaults), PARAM_TEXT, 80, 10));

    $units = [
        'pt' => get_string('unit_pt', 'tiny_fontsize'),
        'px' => get_string('unit_px', 'tiny_fontsize'),
        'em' => get_string('unit_em', 'tiny_fontsize'),
        'rem' => get_string('unit_rem', 'tiny_fontsize'),
        '%' => get_string('unit_percent', 'tiny_fontsize'),
    ];

    $settings->add(new admin_setting_configselect(
        'tiny_fontsize/fontsizeunit',
        get_string('fontsizeunit', 'tiny_fontsize'),
        get_string('fontsizeunit_desc', 'tiny_fontsize'),
        'pt',
        $units
    ));

    // Licensing settings
    $settings->add(new admin_setting_heading(
        'tiny_fontsize/licensingheading',
        get_string('licensingheading', 'tiny_fontsize'),
        ''
    ));

    $licensekeysetting = new admin_setting_configtext(
        'tiny_fontsize/license_key',
        get_string('license_key', 'tiny_fontsize'),
        get_string('license_key_desc', 'tiny_fontsize'),
        '',
        PARAM_RAW_TRIMMED
    );
    // Validate immediately whenever the license key is changed and saved, rather
    // than waiting for the next scheduled run.
    $licensekeysetting->set_updatedcallback(static function() {
        core_php_time_limit::raise(60);
        ob_start();
        (new \tiny_fontsize\task\validate_license())->execute();
        ob_end_clean();
    });
    $settings->add($licensekeysetting);

    // License validation information (read-only)
    $validationData = get_config('tiny_fontsize', 'license_validation_data');
    $lastChecked = get_config('tiny_fontsize', 'license_last_checked');
    $validationError = get_config('tiny_fontsize', 'license_validation_error');

    $infoText = '';
    if ($validationError) {
        $infoText = get_string('validation_error', 'tiny_fontsize') . ': ' . $validationError;
    } elseif ($validationData) {
        $data = json_decode($validationData, true);
        $status = $data['status'] ?? 'unknown';
        $valid = $data['valid'] ?? false;
        $expiresAt = $data['expires_at'] ?? null;

        $infoText = get_string('license_status', 'tiny_fontsize') . ': ' . $status . "<br>";
        $infoText .= get_string('license_valid', 'tiny_fontsize') . ': ' . ($valid ? get_string('yes') : get_string('no')) . "<br>";
        if ($expiresAt) {
            $infoText .= get_string('license_expires', 'tiny_fontsize') . ': ' . date('Y-m-d', $expiresAt) . "<br>";
        }
    }

    if ($lastChecked) {
        $infoText .= "<br>" . get_string('last_validated', 'tiny_fontsize') . ': ' . date('Y-m-d H:i:s', $lastChecked);
    }

    if (trim($infoText)) {
        $settings->add(new admin_setting_heading(
            'tiny_fontsize/validation_info',
            get_string('license_validation_info', 'tiny_fontsize'),
            $infoText
        ));
    }
}
