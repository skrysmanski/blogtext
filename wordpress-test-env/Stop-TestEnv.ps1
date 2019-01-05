#!/usr/bin/env pwsh
param(
    [string] $WordpressVersion = '',

    [string] $PhpVersion = '',

    [string] $ProjectName = 'blogtext'
)

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    if (($WordpressVersion -ne '') -and ($PhpVersion -ne '')) {
        $wordpressTag = "$WordpressVersion-php$PhpVersion"
    }
    elseif ($WordpressVersion -ne '') {
        $wordpressTag = $WordpressVersion
    }
    elseif ($PhpVersion -ne '') {
        $wordpressTag = "php$PhpVersion"
    }
    else {
        $wordpressTag = 'latest'
    }

    $ProjectName = "$ProjectName-wp-$wordpressTag"
    $env:WORDPRESS_WEB_CONTAINER_NAME = "$($ProjectName)_web"
    $env:WORDPRESS_DB_CONTAINER_NAME = "$($ProjectName)_db"

    # Env vars are required to supress warning (they're not used during "down")
    $env:WORDPRESS_DOCKER_TAG = 'xxx'
    $env:WORDPRESS_HOST_PORT = 8080

    & docker-compose --project-name $ProjectName down
    if (-Not $?) {
        throw '"docker-compose down" failed'
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
