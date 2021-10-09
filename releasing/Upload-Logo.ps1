#!/usr/bin/env pwsh

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    $SVN_DIR = 'logo-svn'

    if (-Not (Test-Path "$SVN_DIR/.svn")) {
        Write-Host -ForegroundColor Cyan "The directory '$SVN_DIR' is not an SVN working copy. Creating it."
        Write-Host

        & svn checkout http://plugins.svn.wordpress.org/blogtext/assets $SVN_DIR
        if (-Not $?) {
            throw '"svn checkout" failed'
        }
    }
    else {
        & svn update $SVN_DIR
        if (-Not $?) {
            throw '"svn update" failed'
        }
    }

    # NOTE: Even though WordPress supports svg logos, it doesn't support webfonts in these logos.
    #   So we still have to convert our svg logo into a png.
    & $PSScriptRoot/logo/Create-LogoPng.ps1

    # See: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/#plugin-icons
    Copy-Item 'logo/icon-256x256.png' "$SVN_DIR/"

    Write-Host
    Write-Host -ForegroundColor Cyan 'Uploading changes to WordPress plugin directory...'
    Write-Host

    & $PSScriptRoot/SvnAddRemove.ps1 $SVN_DIR

    & svn commit $SVN_DIR --message "Updated BlogText logo"
    if (-Not $?) {
        throw '"svn commit" failed'
    }

    & svn update $SVN_DIR
    if (-Not $?) {
        throw '"svn update" failed'
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
