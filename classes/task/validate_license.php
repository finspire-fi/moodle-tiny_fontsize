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
 * Scheduled task to validate the license for Tiny font size plugin.
 *
 * @package     tiny_fontsize
 * @copyright   2025 Mikko Haiku <mikko.haiku@iki.fi>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_fontsize\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to validate the license for Tiny font size plugin.
 */
class validate_license extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown in admin UI).
     */
    public function get_name() {
        return get_string('task_validate_license', 'tiny_fontsize');
    }

    /**
     * Get the component name.
     */
    public function get_component() {
        return 'tiny_fontsize';
    }

    /**
     * Execute the task - validate the license via the licensing server.
     */
    public function execute() {
        global $CFG;

        $license_key = get_config('tiny_fontsize', 'license_key');

        if (empty($license_key)) {
            mtrace('No license key configured for tiny_fontsize');
            return;
        }

        $this->validate_license_via_api($license_key);
    }

    /**
     * Maximum number of attempts to contact the licensing API before giving up.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Base delay (in seconds) between retries. Doubles after each failed attempt.
     */
    private const RETRY_BASE_DELAY = 2;

    /**
     * Validate the license via the licensing API, retrying transient failures
     * with an exponential backoff before giving up.
     *
     * @param string $license_key The license key to validate.
     */
    private function validate_license_via_api($license_key) {
        $attempt = 0;

        do {
            $attempt++;
            $result = $this->perform_request($license_key);

            $shouldretry = $result['curl_error'] !== ''
                || $result['http_code'] === 0
                || $result['http_code'] >= 500;

            if (!$shouldretry) {
                break;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                $delay = self::RETRY_BASE_DELAY * (2 ** ($attempt - 1));
                mtrace(
                    "License validation attempt {$attempt} failed for tiny_fontsize, " .
                    "retrying in {$delay}s..."
                );
                sleep($delay);
            }
        } while ($attempt < self::MAX_ATTEMPTS);

        $response = $result['response'];
        $http_code = $result['http_code'];
        $curl_error = $result['curl_error'];

        if ($curl_error) {
            mtrace("License validation error for tiny_fontsize: CURL error - $curl_error (after {$attempt} attempt(s))");
            set_config('license_validation_error', $curl_error, 'tiny_fontsize');
            set_config('license_last_checked', time(), 'tiny_fontsize');
            return;
        }

        if ($http_code !== 200) {
            mtrace("License validation error for tiny_fontsize: HTTP $http_code (after {$attempt} attempt(s))");
            set_config('license_validation_error', "HTTP $http_code", 'tiny_fontsize');
            set_config('license_last_checked', time(), 'tiny_fontsize');
            return;
        }

        $data = json_decode($response, true);
        if (!$data) {
            mtrace("License validation error for tiny_fontsize: Invalid JSON response");
            set_config('license_validation_error', 'Invalid JSON response', 'tiny_fontsize');
            set_config('license_last_checked', time(), 'tiny_fontsize');
            return;
        }

        // Save validation data
        set_config('license_validation_error', '', 'tiny_fontsize');
        set_config('license_validation_data', json_encode($data), 'tiny_fontsize');
        set_config('license_last_checked', time(), 'tiny_fontsize');

        $is_valid = $data['valid'] ?? false;
        if ($is_valid) {
            mtrace("License validation successful for tiny_fontsize");
        } else {
            mtrace("License validation failed for tiny_fontsize: License is not valid");
        }
    }

    /**
     * Perform a single HTTP request against the licensing API.
     *
     * @param string $license_key The license key to validate.
     * @return array{response: string|false, http_code: int, curl_error: string}
     */
    private function perform_request($license_key) {
        $api_url = 'https://api.finspire.fi/v1/licenses/verify';

        $payload = json_encode(['license_key' => $license_key]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        return [
            'response' => $response,
            'http_code' => $http_code,
            'curl_error' => $curl_error,
        ];
    }
}
