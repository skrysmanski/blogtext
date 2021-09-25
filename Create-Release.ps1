#!/usr/bin/env pwsh
param(
    [Parameter(Mandatory=$True)]
    [string] $Version
)

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    $DEST_DIR = 'dist'
    $CSS_FILE = 'style/blogtext-default.css'

    if (-Not (Test-Path "$DEST_DIR/.svn")) {
        Write-Host -ForegroundColor Cyan "The directory '$DEST_DIR' is not an SVN working copy. Creating it."
        Write-Host

        & svn checkout https://plugins.svn.wordpress.org/blogtext/trunk $DEST_DIR
        if (-Not $?) {
            throw '"svn checkout" failed'
        }
    }
    else {
        & svn update $DEST_DIR
        if (-Not $?) {
            throw '"svn update" failed'
        }
    }

    & rsync --recursive --human-readable --times --delete --exclude=.svn 'src/' "$DEST_DIR/"
    if (-Not $?) {
        throw '"rsync" failed'
    }

    Copy-Item './license.txt' $DEST_DIR

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

    # Create release zip
    New-Item dist-zip -ItemType Directory -ErrorAction SilentlyContinue | Out-Null
    Get-ChildItem $DEST_DIR -Exclude '.svn' | Compress-Archive -DestinationPath "dist-zip/blogtext-$Version.zip"
}
catch {
    function LogError([string] $exception) {
        Write-Host -ForegroundColor Red $exception
    }

    # Type of $_: System.Management.Automation.ErrorRecord

    # NOTE: According to https://docs.microsoft.com/en-us/powershell/scripting/developer/cmdlet/windows-powershell-error-records
    #   we should always use '$_.ErrorDetails.Message' instead of '$_.Exception.Message' for displaying the message.
    #   In fact, there are cases where '$_.ErrorDetails.Message' actually contains more/better information than '$_.Exception.Message'.
    if ($_.ErrorDetails -And $_.ErrorDetails.Message) {
        $unhandledExceptionMessage = $_.ErrorDetails.Message
    }
    elseif ($_.Exception -And $_.Exception.Message) {
        $unhandledExceptionMessage = $_.Exception.Message
    }
    else {
        $unhandledExceptionMessage = 'Could not determine error message from ErrorRecord'
    }

    # IMPORTANT: We compare type names(!) here - not actual types. This is important because - for example -
    #   the type 'Microsoft.PowerShell.Commands.WriteErrorException' is not always available (most likely
    #   when Write-Error has never been called).
    if ($_.Exception.GetType().FullName -eq 'Microsoft.PowerShell.Commands.WriteErrorException') {
        # Print error messages (without stacktrace)
        LogError $unhandledExceptionMessage
    }
    else {
        # Print proper exception message (including stack trace)
        # NOTE: We can't create a catch block for "RuntimeException" as every exception
        #   seems to be interpreted as RuntimeException.
        if ($_.Exception.GetType().FullName -eq 'System.Management.Automation.RuntimeException') {
            LogError "$unhandledExceptionMessage$([Environment]::NewLine)$($_.ScriptStackTrace)"
        }
        else {
            LogError "$($_.Exception.GetType().Name): $unhandledExceptionMessage$([Environment]::NewLine)$($_.ScriptStackTrace)"
        }
    }

    exit 1
}
