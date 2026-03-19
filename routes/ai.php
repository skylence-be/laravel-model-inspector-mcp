<?php

use Laravel\Mcp\Facades\Mcp;
use Skylence\EloquentMcp\Mcp\Servers\EloquentServer;

Mcp::local('eloquent', EloquentServer::class);
