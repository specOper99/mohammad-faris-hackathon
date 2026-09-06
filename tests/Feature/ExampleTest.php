<?php

test('welcome page loads', function () {
    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/')
        ->assertOk();
});
