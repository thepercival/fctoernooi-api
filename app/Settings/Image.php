<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-1-17
 * Time: 21:20
 */

namespace App\Settings;

class Image
{
	/**
	 * @var string
	 */
	protected $sponsorsPathPostfix;
    /**
     * @var string
     */
    protected $sponsorsBackupPath;

	public function __construct( array $settings )
	{
	    if( array_key_exists( 'sponsors', $settings ) ) {
            $sponsorSettings = $settings['sponsors'];
	        if( array_key_exists( 'pathpostfix', $sponsorSettings ) ) {
                $this->sponsorsPathPostfix = $sponsorSettings['pathpostfix'];
            }
            if( array_key_exists( 'backuppath', $sponsorSettings ) ) {
                $this->sponsorsBackupPath = $sponsorSettings['backuppath'];
            }
        }
	}

	public function getSponsorsPathPostfix(): string {
	    return $this->sponsorsPathPostfix;
    }

    public function getSponsorsBackupPath(): string {
        return $this->sponsorsBackupPath;
    }
}