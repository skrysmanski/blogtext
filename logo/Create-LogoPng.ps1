#!/usr/bin/env pwsh

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    $SOURCE_FILE = 'logo.svg'
    # Name according to: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/#plugin-icons
    $DEST_FILE = 'icon-256x256.png'

    & yarn install
    if (-Not $?) {
        Write-Error '"yarn install" failed'
    }

    Remove-Item $DEST_FILE -ErrorAction SilentlyContinue | Out-Null

    & ./node_modules/.bin/svg2png $SOURCE_FILE --output $DEST_FILE
    if (-Not $?) {
        Write-Error '"svg2png" failed^'
    }
}
catch {
    # IMPORTANT: We compare type names(!) here - not actual types. This is important because - for example -
    #   the type 'Microsoft.PowerShell.Commands.WriteErrorException' is not always available (most likely
    #   when Write-Error has never been called).
    if ($_.Exception.GetType().FullName -eq 'Microsoft.PowerShell.Commands.WriteErrorException') {
        # Print error messages (without stacktrace)
        Write-Host -ForegroundColor Red $_.Exception.Message
    }
    else {
        # Print proper exception message (including stack trace)
        # NOTE: We can't create a catch block for "RuntimeException" as every exception
        #   seems to be interpreted as RuntimeException.
        if ($_.Exception.GetType().FullName -eq 'System.Management.Automation.RuntimeException') {
            Write-Host -ForegroundColor Red $_.Exception.Message
        }
        else {
            Write-Host -ForegroundColor Red "$($_.Exception.GetType().Name): $($_.Exception.Message)"
        }
        Write-Host -ForegroundColor Red $_.ScriptStackTrace
    }

    exit 1
}
