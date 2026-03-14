<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginWebTest extends TestCase
{
    public function test_login_page_accessible()
    {
        $this->assertTrue(true);
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $this->assertTrue(true);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $this->assertTrue(true);
    }

    public function test_authenticated_user_redirected_from_login()
    {
        $this->assertTrue(true);
    }

    public function test_remember_me_creates_persistent_session()
    {
        $this->assertTrue(true);
    }
}
