<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 12:23
 */

namespace App\Actions;

use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use \Suin\ImageResizer\ImageResizer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Sponsor as SponsorBase;
use App\Settings\Www as WwwSettings;
use App\Settings\Image as ImageSettings;

final class Sponsor extends Action
{
    /**
     * @var SponsorRepository
     */
    private $sponsorRepos;
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var WwwSettings
     */
    protected $wwwSettings;
    /**
     * @var ImageSettings
     */
    protected $imageSettings;

    const LOGO_ASPECTRATIO_THRESHOLD = 0.34;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        SponsorRepository $sponsorRepos,
        TournamentRepository $tournamentRepos,
        WwwSettings $wwwSettings,
        ImageSettings $imageSettings
    )
    {
        parent::__construct($logger,$serializer);
        $this->sponsorRepos = $sponsorRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->wwwSettings = $wwwSettings;
        $this->imageSettings = $imageSettings;
    }

    public function fetch( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");
            $json = $this->serializer->serialize( $tournament->getSponsors(), 'json');
            return $this->respondWithJson( $response, $json );
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function fetchOne( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $sponsor = $this->sponsorRepos->find((int) $args['sponsorId']);
            if ( $sponsor === null ) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            if ( $sponsor->getTournament() !== $tournament ) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }
            $json = $this->serializer->serialize( $sponsor, 'json');
            return $this->respondWithJson( $response, $json );
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function add( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var \FCToernooi\Sponsor $sponsor */
            $sponsor = $this->serializer->deserialize( $this->getRawData(), 'FCToernooi\Sponsor', 'json');

            $this->sponsorRepos->checkNrOfSponsors($tournament, $sponsor->getScreenNr() );

            $newSponsor = new SponsorBase( $tournament, $sponsor->getName() );
            $newSponsor->setUrl( $sponsor->getUrl() );
            $newSponsor->setLogoUrl( $sponsor->getLogoUrl() );
            $newSponsor->setScreenNr( $sponsor->getScreenNr() );
            $this->sponsorRepos->save($newSponsor);

            $json = $this->serializer->serialize( $newSponsor, 'json');
            return $this->respondWithJson( $response, $json );
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var \FCToernooi\Sponsor $sponsorSer */
            $sponsorSer = $this->serializer->deserialize( $this->getRawData(), 'FCToernooi\Sponsor', 'json');

            $sponsor = $this->sponsorRepos->find((int) $args['sponsorId']);
            if ( $sponsor === null ) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            if ( $sponsor->getTournament() !== $tournament ) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }

            $this->sponsorRepos->checkNrOfSponsors($tournament, $sponsorSer->getScreenNr(), $sponsor );

            $sponsor->setName( $sponsorSer->getName() );
            $sponsor->setUrl( $sponsorSer->getUrl() );
            $sponsor->setLogoUrl( $sponsorSer->getLogoUrl() );
            $sponsor->setScreenNr( $sponsorSer->getScreenNr() );
            $this->sponsorRepos->save($sponsor);

            $json = $this->serializer->serialize( $sponsor, 'json');
            return $this->respondWithJson( $response, $json );
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function remove( Request $request, Response $response, $args ): Response
    {
        try{
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $sponsor = $this->sponsorRepos->find((int) $args['sponsorId']);
            if ( $sponsor === null ) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            if ( $sponsor->getTournament() !== $tournament ) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }

            $this->sponsorRepos->remove( $sponsor );

            return $response->withStatus(200);
        }
        catch( \Exception $e ){
            return new ErrorResponse( $e->getMessage(), 404);
        }
    }

    public function upload( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $sponsor = $this->sponsorRepos->find((int) $args['sponsorId']);
            if ( $sponsor === null ) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            if ( $sponsor->getTournament() !== $tournament ) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }

            $uploadedFiles = $request->getUploadedFiles();
            if( !array_key_exists("logostream", $uploadedFiles ) ) {
                throw new \Exception("geen goede upload gedaan, probeer opnieuw", E_ERROR);
            }
            $logostream = $uploadedFiles["logostream"];
            $extension = null;
            if( $logostream->getClientMediaType() === "image/jpeg" ) {
                $extension = "jpg";
            } else if( $logostream->getClientMediaType() === "image/png" ) {
                $extension = "png";
            } else if( $logostream->getClientMediaType() === "image/gif" ) {
                $extension = "gif";
            } else {
                throw new \Exception("alleen jpg en png zijn toegestaan", E_ERROR);
            }

            $localPath = $this->wwwSettings->getApiUrlLocalPath() . $this->imageSettings->getSponsorsPathPostfix();
            $urlPath = $this->wwwSettings->getApiUrl() . $this->imageSettings->getSponsorsPathPostfix();

            $logoUrl = $urlPath . $sponsor->getId() . '.' . $extension;

            $newImagePath = $localPath . $sponsor->getId() . '.' . $extension;
            $source_properties = getimagesize($logostream->file);
            $image_type = $source_properties[2];
            if( $image_type == IMAGETYPE_JPEG ) {
                $image_resource_id = imagecreatefromjpeg($logostream->file);
                $target_layer = $this->fn_resize($image_resource_id,$source_properties[0],$source_properties[1]);
                imagejpeg($target_layer,$newImagePath);
            }
            elseif( $image_type == IMAGETYPE_GIF )  {
                $image_resource_id = imagecreatefromgif($logostream->file);
                $target_layer = $this->fn_resize($image_resource_id,$source_properties[0],$source_properties[1]);
                imagegif($target_layer,$newImagePath);
            }
            elseif( $image_type == IMAGETYPE_PNG ) {
                $image_resource_id = imagecreatefrompng($logostream->file);
                $target_layer = $this->fn_resize($image_resource_id,$source_properties[0],$source_properties[1]);
                imagepng($target_layer,$newImagePath);
            }

            $sponsor->setLogoUrl( $logoUrl );
            $this->sponsorRepos->save($sponsor);

            $json = $this->serializer->serialize( $sponsor, 'json');
            return $this->respondWithJson( $response, $json );
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    private function fn_resize($image_resource_id,$width,$height) {

        $target_height = 200;
        if( $height === $target_height ) {
            return $image_resource_id;
        }
        $thressHold = Sponsor::LOGO_ASPECTRATIO_THRESHOLD;
        $aspectRatio = $width / $height;

        $target_width = $width - (( $height - $target_height ) * $aspectRatio );
        if( $target_width < ( $target_height * ( 1 - $thressHold ) ) ) {
            $target_width = $target_height * ( 1 - $thressHold );
        } else if( $target_width > ( $target_height * ( 1 + $thressHold ) ) ) {
            $target_width = $target_height * ( 1 + $thressHold );
        }
        return $this->fn_resize_helper($image_resource_id,$width,$height,$target_width,200);
        /*else if( $height < $target_height ) { // make image larger
            $target_width = $width - (( $height - $target_height ) * $aspectRatio );
            $new_image_resource_id = fn_resize_helper($image_resource_id,$width,$height,$target_width,200)
        }*/

    }

    private function fn_resize_helper($image_resource_id,$width,$height,$target_width,$target_height) {
        $target_layer = imagecreatetruecolor($target_width,$target_height);
        imagecopyresampled($target_layer,$image_resource_id,0,0,0,0,$target_width,$target_height, $width,$height);
        return $target_layer;
    }
}