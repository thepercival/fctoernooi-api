<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 26-11-17
 * Time: 12:35
 */

namespace App\Actions\Voetbal;

use JMS\Serializer\Serializer;
use Voetbal\Field\Repository as FieldRepository;
use Voetbal\Sport\Repository as SportRepository;
// use Voetbal\Field\Service as FieldService;
use Voetbal\Competition\Repository as CompetitionRepos;
use Voetbal\Field as FieldBase;

final class Field
{
    /**
     * @var FieldRepository
     */
    protected $repos;
    /**
     * @var SportRepository
     */
    protected $sportRepos;
    /**
     * @var CompetitionRepos
     */
    protected $competitionRepos;
    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        FieldRepository $repos,
        SportRepository $sportRepos,
        CompetitionRepos $competitionRepos,
        Serializer $serializer
    ) {
        $this->repos = $repos;
        $this->sportRepos= $sportRepos;
        $this->competitionRepos = $competitionRepos;
        $this->serializer = $serializer;
    }

    public function fetch( $request, $response, $args)
    {
        $objects = $this->repos->findAll();
        return $response
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->write( $this->serializer->serialize( $objects, 'json') );
        ;

    }

    public function fetchOne( $request, $response, $args)
    {
        $object = $this->repos->find($args['id']);
        if ($object) {
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $object, 'json'));
            ;
        }
        return $response->withStatus(404)->write('geen scheidsrechter met het opgegeven id gevonden');
    }

    public function add($request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            /** @var \Voetbal\Field|false $fieldSer */
            $fieldSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Field', 'json');
            if ($fieldSer === false) {
                throw new \Exception("er kan geen veld worden aangemaakt o.b.v. de invoergegevens", E_ERROR);
            }
            $competition = $this->competitionRepos->find((int)$request->getParam("competitionid"));
            if ($competition === null) {
                throw new \Exception("de competitie kan niet gevonden worden", E_ERROR);
            }
            $fieldsWithSameName = $competition->getFields()->filter( function( $fieldIt ) use ( $fieldSer ) {
                return $fieldIt->getName() === $fieldSer->getName() || $fieldIt->getNumber() === $fieldSer->getNumber();
            });
            if( !$fieldsWithSameName->isEmpty() ) {
                throw new \Exception("het veldnummer \"".$fieldSer->getNumber()."\" of de veldnaam \"".$fieldSer->getName()."\" bestaat al", E_ERROR );
            }
            $sport = $this->sportRepos->find($fieldSer->getSportIdSer());
            if ( $sport === null ) {
                throw new \Exception("de sport kan niet gevonden worden", E_ERROR);
            }

            $field = new FieldBase( $competition, $fieldSer->getNumber() );
            $field->setName( $fieldSer->getName() );
            $field->setSport( $sport );

            $this->repos->save( $field );
            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($field, 'json'));;
        } catch (\Exception $e) {
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write($sErrorMessage);
    }

    public function edit($request, $response, $args)
    {
        try {
            $field = $this->getField((int)$args["id"], (int)$request->getParam("competitionid"));
            /** @var \Voetbal\Field|false $fieldSer */
            $fieldSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Field', 'json');
            if ($fieldSer === false) {
                throw new \Exception("het veld kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $competition = $field->getCompetition();
            $fieldsWithSameName = $competition->getFields()->filter( function( $fieldIt ) use ( $fieldSer, $field ) {
                return $fieldIt->getName() === $fieldSer->getName() && $field !== $fieldIt;
            });
            if( !$fieldsWithSameName->isEmpty() ) {
                throw new \Exception("het veld \"".$fieldSer->getName()."\" bestaat al", E_ERROR );
            }

            $sport = $this->sportRepos->find($fieldSer->getSportIdSer());
            if ( $sport === null ) {
                throw new \Exception("de sport kan niet gevonden worden", E_ERROR);
            }
            $field->setName( $fieldSer->getName() );
            $field->setSport( $sport );

            $this->repos->save( $field );
            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($field, 'json'));
        } catch (\Exception $e) {
            return $response->withStatus(404)->write($e->getMessage());
        }

    }

    public function remove($request, $response, $args)
    {
        try {
            $field = $this->getField((int)$args["id"], (int)$request->getParam("competitionid"));
            $this->repos->remove($field);
            return $response->withStatus(204);
        } catch (\Exception $e) {
            return $response->withStatus(404)->write($e->getMessage());
        }
    }

    protected function getField(int $id, int $competitionId): FieldBase
    {
        $field = $this->repos->find($id);
        if ($field === null) {
            throw new \Exception("het veld kon niet gevonden worden o.b.v. de invoer", E_ERROR);
        }
        $competition = $this->competitionRepos->find($competitionId);
        if ($competition === null) {
            throw new \Exception("er kan geen competitie worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        if ($field->getCompetition() !== $competition) {
            throw new \Exception("de competitie van het veld komt niet overeen met de verstuurde competitie",
                E_ERROR);
        }
        return $field;
    }
}