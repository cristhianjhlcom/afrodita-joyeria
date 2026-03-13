<?php

it('renders policy pages in Spanish', function () {
    $routes = [
        route('policies.terms'),
        route('policies.privacy'),
        route('policies.returns'),
        route('policies.shipping'),
    ];

    foreach ($routes as $route) {
        $this->get($route)
            ->assertSuccessful()
            ->assertSee('soporte@ec-shop.store')
            ->assertSee('970 866 290');
    }
});
