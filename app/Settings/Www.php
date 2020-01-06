<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-1-17
 * Time: 21:20
 */

namespace App\Settings;

class Www
{
	/**
     * @var string
     */
	protected $wwwUrl;
    /**
     * @var string
     */
    protected $apiUrl;
    /**
     * @var string
     */
    protected $apiUrlLocalpath;

	public function __construct(array $settings )
	{
	    if( array_key_exists( 'wwwurl', $settings ) ) {
            $this->wwwUrl = $settings['wwwurl'];
        }
        if( array_key_exists( 'apiurl', $settings ) ) {
            $this->apiUrl = $settings['apiurl'];
        }
        if( array_key_exists( 'apiurl-localpath', $settings ) ) {
            $this->apiUrlLocalpath = $settings['apiurl-localpath'];
        }
	}

	public function getWwwUrl(): string {
	    return $this->wwwUrl;
    }

    public function getApiUrl(): string {
        return $this->apiUrl;
    }

    public function getApiUrlLocalpath(): string {
        return $this->apiUrlLocalpath;
    }
}