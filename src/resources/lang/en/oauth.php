<?php

// The consent page of OAuth for the MCP server (decision 0028): `panneau::oauth.*`, overridden
// in lang/vendor/panneau/{locale}/oauth.php
return [
    'title' => 'Authorize :client',
    'heading' => ':client wants to connect to your account',
    'reads' => 'It will be able to read your content.',
    'writes' => 'It will be able to read and change your content.',
    'user' => 'It will act as :user, with your permissions.',
    'refuse' => 'Refuse',
    'authorize' => 'Authorize',
];
