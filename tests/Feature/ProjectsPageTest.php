<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the projects page through Inertia', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Projects/Index'));
});
