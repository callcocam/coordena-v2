<?php

it('renders the public home page', function () {
    $this->get(route('home'))->assertOk();
});

it('renders the terms page', function () {
    $this->get(route('legal.terms'))->assertOk();
});

it('renders the privacy page', function () {
    $this->get(route('legal.privacy'))->assertOk();
});
