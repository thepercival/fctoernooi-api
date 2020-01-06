<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:29
 */

namespace App\Actions;

use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use FCToernooi\Role\Repository as RoleRepository;

final class Role extends Action
{
    /**
     * @var RoleRepository
     */
    private $roleRepository;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        RoleRepository $roleRepository
    )
    {
        parent::__construct($logger,$serializer);
        $this->roleRepository = $roleRepository;
    }

    public function fetch($request, $response, $args)
    {
        $users = $this->roleRepository->findAll();
        return $response
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->write($this->serializer->serialize( $users, 'json'));
        ;
    }

    public function fetchOne($request, $response, $args)
    {
        $role = $this->roleRepository->find($args['id']);
        if ($role) {
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $role, 'json'));
            ;
        }
        return $response->withStatus(404)->write('geen toernooirol met het opgegeven id gevonden');
    }
}