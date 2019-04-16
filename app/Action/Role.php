<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:29
 */

namespace App\Action;

use JMS\Serializer\Serializer;
use FCToernooi\Role\Repository as RoleRepository;

final class Role
{
    /**
     * @var RoleRepository
     */
    private $roleRepository;
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var array
     */
    protected $settings;

    public function __construct(RoleRepository $roleRepository, Serializer $serializer, $settings )
    {
        $this->roleRepository = $roleRepository;
        // $this->authService = new Auth\Service($userRepository);
        $this->serializer = $serializer;
        $this->settings = $settings;
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