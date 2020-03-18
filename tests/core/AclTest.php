<?php

namespace mii\tests\web;

use mii\core\ACL;
use mii\tests\TestCase;


class AclTest extends TestCase
{


    public function testDefault() {
        $acl = new ACL();
        $this->assertFalse($acl->check('*'));
    }


    public function testRules() {
        $acl = new ACL();
        $acl->allow('admin', '*');
        $acl->allow('guest', 'testRules');
        $acl->allow('foo', 'bar');


        $this->assertTrue($acl->check('admin'));
        $this->assertTrue($acl->check('guest', 'testRules'));
        $this->assertFalse($acl->check('guest', 'bar'));
        $this->assertTrue($acl->check('foo', 'bar'));
        $this->assertFalse($acl->check('foo',  'bar2'));

        $this->assertTrue($acl->check(['admin', 'foo'], 'bar'));
    }


}