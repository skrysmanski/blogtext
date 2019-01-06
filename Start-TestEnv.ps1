#!/usr/bin/env pwsh
& $PSScriptRoot/wordpress-test-env/Start-TestEnv.ps1 -ProjectFile "$PSScriptRoot/wordpress-env.json" @args
