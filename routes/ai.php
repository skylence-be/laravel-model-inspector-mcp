<?php

use Laravel\Mcp\Facades\Mcp;
use Skylence\ModelInspectorMcp\Mcp\Servers\ModelInspectorServer;

Mcp::local('eloquent', ModelInspectorServer::class);
