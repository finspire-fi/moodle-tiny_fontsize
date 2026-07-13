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
$ADMIN->add('editortiny', new admin_category($plugin, new lang_string('pluginname', $plugin)));

$settings = new admin_settingpage('tiny_fontsize_settings', new lang_string('settings', $plugin));
if ($ADMIN->fulltree) {
    // Licensing settings
    $settings->add(new admin_setting_heading(
        'tiny_fontsize/licensingheading',
        get_string('licensingheading', 'tiny_fontsize'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'tiny_fontsize/license_key',
        get_string('license_key', 'tiny_fontsize'),
        get_string('license_key_desc', 'tiny_fontsize'),
        '',
        PARAM_RAW_TRIMMED
    ));

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
        $infoText .= get_string('last_validated', 'tiny_fontsize') . ': ' . date('Y-m-d H:i:s', $lastChecked);
    }

    if (trim($infoText)) {
        $settings->add(new admin_setting_heading(
            'tiny_fontsize/validation_info',
            get_string('license_validation_info', 'tiny_fontsize'),
            $infoText
        ));
    }
}

// Add the settings page under the plugin category so it doesn't create
// a second top-level settings entry with the same name.
$ADMIN->add($plugin, $settings);
