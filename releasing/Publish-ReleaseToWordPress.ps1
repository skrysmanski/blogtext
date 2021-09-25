#!/usr/bin/env pwsh
param(
    [Parameter(Mandatory=$True)]
    [string] $Version
)

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    $REPO_URI = 'https://plugins.svn.wordpress.org/blogtext'
    $DEST_DIR = 'dist'

    if (-Not (Test-Path "$DEST_DIR/.svn")) {
        Write-Error "'$DEST_DIR' does not exist or is no Subversion repository. Did you run 'Create-Release.ps1' before?"
    }

    Push-Location $DEST_DIR

    $script:ErrorActionPreference = 'SilentlyContinue'
    & svn info "$REPO_URI/tags/$Version" 2>&1 | Out-Null
    $script:ErrorActionPreference = 'Stop'

    if ($LASTEXITCODE -eq 0) {
        Write-Error "A tag for version '$Version' already exists."
    }

    Write-Host -ForegroundColor Cyan 'Uploading changes to WordPress plugin directory (trunk)...'
    Write-Host

    & svn commit --message "Updated BlogText to $Version"
    if (-Not $?) {
        throw '"svn commit" failed'
    }

    & svn update
    if (-Not $?) {
        throw '"svn update" failed'
    }

    Write-Host
    Write-Host -ForegroundColor Cyan 'Creating release tag...'
    Write-Host

    & svn copy "$REPO_URI/trunk/" "$REPO_URI/tags/$Version" --message "Created release tag for BlogText version $Version"
    if (-Not $?) {
        throw '"svn copy" failed'
    }
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
finally {
    Pop-Location
}
