<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('pages::index')
        ->assertStatus(200);
});
