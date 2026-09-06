<?php

test('welcome page loads', function () {
    $this->get('/')->assertOk();
});
