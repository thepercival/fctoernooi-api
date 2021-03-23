<?php
declare(strict_types=1);

namespace FCToernooiTest\Auth;

use \FCToernooi\User as User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailaddress(): void
    {
        $user = new User("cdk@gmail.com");
        self::assertSame("cdk@gmail.com", $user->getEmailaddress());
    }

    public function testCreateNameMin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $user = new User("cdk@gmail.com");
        $user->setName("12");
    }

    public function testCreateNameMax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $user = new User("cdk@gmail.com");
        $user->setName("1234567890123456");
    }

    public function testCreateNameAlphaN(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $user = new User("cdk@gmail.com");
        $user->setName("12AA.");
    }
}
