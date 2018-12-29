#!/usr/bin/env pwsh

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    $DEST_DIR = 'dist'
    $CSS_FILE = 'style/blogtext-default.css'

    New-Item $DEST_DIR -ItemType Directory -ErrorAction SilentlyContinue | Out-Null

    & rsync --recursive --human-readable --times --delete --exclude=.svn 'src/' "$DEST_DIR/"
    if (-Not $?) {
        throw '"rsync" failed'
    }

    # Install all necessary node modules via package.json
    & yarn install
    if (-Not $?) {
        throw '"npm install" failed'
    }

    # Minify CSS file
    & node_modules/.bin/cleancss -o "$DEST_DIR/$CSS_FILE" "src/$CSS_FILE"
    if (-Not $?) {
        throw '"cleancss" failed'
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
