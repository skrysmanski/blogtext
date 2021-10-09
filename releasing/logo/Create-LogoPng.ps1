#!/usr/bin/env pwsh

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    $SOURCE_FILE = 'logo.svg'
    # Name according to: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/#plugin-icons
    $DEST_FILE = 'icon-256x256.png'

    Push-Location $PSScriptRoot

    if (-Not (Test-Path './node_modules/.bin/svg2png')) {
        & npm install --prefix ./ svg2png
        if (-Not $?) {
            Write-Error '"npm install svg2png" failed'
        }
    }

    Remove-Item $DEST_FILE -ErrorAction SilentlyContinue | Out-Null

    # Fix for error "DSO support routines:DLFCN_LOAD:could not load the shared library:dso_dlfcn.c:185:filename(libssl_conf.so): libssl_conf.so:"
    $env:OPENSSL_CONF = '/etc/ssl/'

    & ./node_modules/.bin/svg2png $SOURCE_FILE --output $DEST_FILE
    if (-Not $?) {
        Write-Error '"svg2png" failed^'
    }
}
finally {
    Pop-Location
}
