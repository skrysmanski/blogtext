#!/usr/bin/env pwsh
& $PSScriptRoot/wordpress-test-env/Stop-TestEnv.ps1 -ProjectFile "$PSScriptRoot/wordpress-env.json" @args
