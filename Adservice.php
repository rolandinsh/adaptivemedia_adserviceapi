<?php

/*
 * Plugin Name:   Adservice Service (Publisher)
 * Version:       0.0.2
 * Plugin URI:    https://simplemediacode.com/
 * Description:   Getting data from Adservice API
 * Author:        Rolands Umbrovskis
 * Author URI:    https://umbrovskis.com
 *
 * License:       GNU General Public License
 *
 */
if (!defined('ABSPATH')) {
    die('Nice try!');
}
try {
    new Adservice();
} catch (\Throwable $th) {
    $adservice_debug =
        'Caught exception: adaptivemedia\Adservice ' . $th->getMessage() . "\n";
    if (
        apply_filters(
            'adservice_debug_log',
            defined('WP_DEBUG_LOG') && WP_DEBUG_LOG
        )
    ) {
        error_log(print_r(compact('adservice_debug'), true));
    }
}

class Adservice
{
    // var $apikey; would get from .env or encoded in database
    var $apiserver = 'https://api.adservice.com/v2/publisher/';

    public function __construct()
    {
        add_shortcode('adservice', [&$this, 'doShortcode'], 15);
    }

    /**
     * Getting enviroment variables
     *
     * @param   String              $name       desired env value lookup
     * @param   String|Boolean|Int  $default    if env variable not found
     * @param   false                           default value
     *
     * @return  Boolean|String|Int              any value, default bool (false)
     *
     * @author  Rolands Umbrovskis
     */
    public function env(string $name, $default = false)
    {
        $env_name =
            isset($_ENV[(string) $name]) && !empty($_ENV[(string) $name])
                ? $_ENV[(string) $name]
                : $default;
        return $env_name;
    }

    public function getData(string $endpoint = '', string $apikey = '')
    {
        $wp_request_options = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('api:' . $apikey),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30, // 30 seconds for larger data
        ];

        // let's start out output journey
        $display = '<p>DATA from API</p>';

        try {
            $allowed_endpoints = apply_filters('adservice_allowed_endpoints', [
                'campaigns/feeds',
                'campaigns/active',
                'account/apikeys',
                'account/medias',
            ]);
            if (in_array($endpoint, $allowed_endpoints)) {
                $response = wp_remote_get(
                    $this->apiserver . $endpoint,
                    $wp_request_options
                );
                $response_code = (int) wp_remote_retrieve_response_code(
                    $response
                );

                /**
                 * We care only about 200-399 codes
                 *
                 * @var Int
                 */
                if ($response_code >= 200 && $response_code < 400) {
                    $response_body = wp_remote_retrieve_body($response);
                    $response_decode = json_decode(
                        mb_convert_encoding($response_body, 'UTF-8', 'auto'),
                        true
                    );

                    if (
                        !empty($response_decode['data']) &&
                        (bool) $response_decode['success']
                    ) {
                        $listdata = $response_decode['data'];

                        try {
                            switch ($endpoint) {
                                case 'campaigns/feeds':
                                case 'campaigns/active':
                                    $display .= $this->displayTableData(
                                        $listdata,
                                        $endpoint
                                    );
                                    break;
                                case 'account/apikeys':
                                    $display .= $this->displayTableData(
                                        (array) $listdata,
                                        $endpoint
                                    );
                                    break;
                                case 'account/medias':
                                    $display .= $this->displayTableData(
                                        $listdata['medias'],
                                        $endpoint
                                    );
                                    break;

                                default:
                                    $display .=
                                        '<pre>' .
                                        print_r($listdata, true) .
                                        '</pre>';
                                    break;
                            }
                        } catch (\Throwable $th) {
                            $display .= $th->getMessage();
                        }
                    } else {
                        $display .=
                            'Feed list was empty un without success returned data.' .
                            "\n" .
                            'Message from API:' .
                            esc_attr($response_decode['message']);
                    }
                } else {
                    $display .=
                        'Adservice Plugin ERROR: could not receive data from API. ' .
                        '<br />response code:' .
                        $response_code .
                        '<br />' .
                        wp_remote_retrieve_response_message($response);
                }
            } else {
                $display .=
                    'Adservice Plugin ERROR: endpoint is not in allowed list';
            }
        } catch (\Throwable $th) {
            $display .= $th->getMessage();
        }
        return $display;
    }

    /**
     * Draw table from data
     *
     * @param   Array   $listdata  List of data to desplay
     * @param   String  $title     (Optional) Title above table
     *
     * @return  String             Empty string or <table>...</table>
     */
    public function displayTableData(array $listdata = [], string $title = '')
    {
        $tabledata = '';
        if (!empty($listdata)) {
            $tabledata .= empty($title) ? '' : "<h2>${title}</h2>";
            $tabledata .= '<table>';
            // since we do not know which data we will display, no <thead></thead> and <th></th> was used
            $tabledata .= '<tbody>';
            foreach ($listdata as $id => $feed) {

                $display_table = '<tr class="list-' . $id . '">';

                // not sanitized, but we could :) with loop
                $display_table .=
                    '<td>' . implode('</td><td>', $feed) . '</td>' . "\n";

                $display_table .= '</tr>';

                $tabledata .= $display_table;
            }
            $tabledata .= '</tbody></table>' . "\n\n";
        }

        return $tabledata;
    }

    public function doShortcode($atts)
    {
        // Attributes
        $atts = shortcode_atts(
            [
                'apikey' => '',
                'service' => '',
            ],
            $atts,
            'adservice'
        );
        return $this->getData($atts['service'], $atts['apikey']);
    }
}
