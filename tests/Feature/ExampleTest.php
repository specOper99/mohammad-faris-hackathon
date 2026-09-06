<?php

test('welcome page loads', function () {
    $this->get('/')->assertOk();
});

test('docs path redirects to scramble ui', function () {
    $this->get('/docs')->assertRedirect('/docs/api');
});
