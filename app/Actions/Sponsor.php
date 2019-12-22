<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 12:23
 */

namespace App\Actions;

use \Suin\ImageResizer\ImageResizer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JMS\Serializer\Serializer;
use FCToernooi\Auth\Service as AuthService;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Sponsor as SponsorBase;

final class Sponsor extends Action
{
    /**
     * @var SponsorRepository
     */
    private $sponsorRepos;
    /**
     * @var AuthService
     */
    private $authService;
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var array
     */
    protected $settings;

    const LOGO_ASPECTRATIO_THRESHOLD = 0.34;

    public function __construct(
        SponsorRepository $sponsorRepos,
        TournamentRepository $tournamentRepos,
        AuthService $authService,
        Serializer $serializer,
        AuthSettings $settings
    )
    {
        $this->sponsorRepos = $sponsorRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->authService = $authService;
        $this->serializer = $serializer;
        $this->settings = $settings;
    }

    public function fetch( Request $request, Response $response, $args ): Response
    {
        try {
            $tournamentId = (int)$request->getParam("tournamentid");
            $tournament = $this->tournamentRepos->find($tournamentId);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            $json = $this->serializer->serialize( $tournament->getSponsors(), 'json');
            return $this->respondWithJson( $response, $json );
        }
        catch( \Exception $e ){
            return $response->withStatus(422)->write( $e->getMessage() );
        }
    }

    public function fetchOne( Request $request, Response $response, $args ): Response
    {
        $sErrorMessage = null;
        try {
            $tournamentId = (int)$request->getParam("tournamentid");
            $tournament = $this->tournamentRepos->find($tournamentId);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $sponsor = $this->sponsorRepos->find($args['id']);
            if (!$sponsor) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sponsor, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage);
    }

    public function add( $request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $tournamentId = (int)$request->getParam("tournamentid");
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->tournamentRepos->find($tournamentId);
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $user = $this->authService->checkAuth( $request );

            /** @var \FCToernooi\Sponsor $sponsorSer */
            $sponsorSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'FCToernooi\Sponsor', 'json');

            $this->repos->checkNrOfSponsors($tournament, $sponsorSer->getScreenNr() );

            $sponsor = new SponsorBase( $tournament, $sponsorSer->getName() );
            $sponsor->setUrl( $sponsorSer->getUrl() );
            $sponsor->setLogoUrl( $sponsorSer->getLogoUrl() );
            $sponsor->setScreenNr( $sponsorSer->getScreenNr() );
            $this->repos->save($sponsor);

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sponsor, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422 )->write( $sErrorMessage );
    }

    public function edit( $request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $tournamentId = (int)$request->getParam("tournamentid");
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->tournamentRepos->find($tournamentId);
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $this->authService->checkAuth( $request, $tournament );

            /** @var \FCToernooi\Sponsor $sponsorSer */
            $sponsorSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'FCToernooi\Sponsor', 'json');

            $sponsor = $this->repos->find( $sponsorSer->getId() );
            if ( $sponsor === null ){
                return $response->withStatus(404)->write( "de te wijzigen sponsor kon niet gevonden worden" );
            }

            $this->sponsorRepos->checkNrOfSponsors($tournament, $sponsorSer->getScreenNr(), $sponsor );

            $sponsor->setName( $sponsorSer->getName() );
            $sponsor->setUrl( $sponsorSer->getUrl() );
            $sponsor->setLogoUrl( $sponsorSer->getLogoUrl() );
            $sponsor->setScreenNr( $sponsorSer->getScreenNr() );
            $this->sponsorRepos->save($sponsor);

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sponsor, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage );
    }

    public function remove( $request, $response, $args)
    {
        $errorMessage = null;
        try{
            $tournamentId = (int)$request->getParam("tournamentid");
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->tournamentRepos->find($tournamentId);
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $this->authService->checkAuth( $request, $tournament );

            /** @var \FCToernooi\Sponsor|null $sponsor */
            $sponsor = $this->sponsorRepos->find( $args['id'] );
            if ( $sponsor === null ){
                return $response->withStatus(404)->write("de te verwijderen sponsor kon niet gevonden worden" );
            }
            $this->sponsorRepos->remove( $sponsor );

            return $response->withStatus(200);
        }
        catch( \Exception $e ){
            $errorMessage = $e->getMessage();
        }
        return $response->withStatus(404)->write('de sponsor is niet verwijdered : ' . $errorMessage );
    }

    public function upload( $request, $response, $args)
    {
        try {
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->tournamentRepos->find((int)$request->getParam("tournamentid"));
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $sponsor = $this->sponsorRepos->find((int)$request->getParam("sponsorid"));
            if (!$sponsor) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }

            $this->authService->checkAuth( $request, $tournament );

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

            $localPath = $this->settings["www"]["apiurl-localpath"] . $this->settings["images"]["sponsors"]["pathpostfix"];
            $urlPath = $this->settings["www"]["apiurl"] . $this->settings["images"]["sponsors"]["pathpostfix"];

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

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sponsor, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(422)->write( $e->getMessage() );
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