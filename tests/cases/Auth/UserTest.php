<?php

declare(strict_types=1);

namespace FCToernooiTest\Auth;

use \FCToernooi\User as User;

class UserTest extends \PHPUnit\Framework\TestCase
{
    public function testEmailaddress()
    {
        $user = new User("cdk@gmail.com");
        self::assertSame("cdk@gmail.com", $user->getEmailaddress());
    }

    public function testCreateNameMin()
    {
        $this->expectException(\InvalidArgumentException::class);
        $user = new User("cdk@gmail.com");
        $user->setName("12");
    }

    public function testCreateNameMax()
    {
        $this->expectException(\InvalidArgumentException::class);
        $user = new User("cdk@gmail.com");
        $user->setName("1234567890123456");
    }

    public function testCreateNameAlphaN()
    {
        $this->expectException(\InvalidArgumentException::class);
        $user = new User("cdk@gmail.com");
        $user->setName("12AA.");
    }
}
