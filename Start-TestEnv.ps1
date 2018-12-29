#!/usr/bin/env pwsh
param(
    [string] $WordpressVersion = '',

    [string] $PhpVersion = '',

    [string] $ProjectName = 'blogtext',

    [int] $HostPort = 8080,

    [int] $MaxConnectRetries = 20
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

    $env:WORDPRESS_DOCKER_TAG = $wordpressTag
    $env:WORDPRESS_HOST_PORT = $HostPort

    & docker-compose --project-name $ProjectName up --detach
    if (-Not $?) {
        throw '"docker-compose up" failed'
    }

    Write-Host
    Write-Host 'Waiting for container to come up...'

    for ($i = 0; $i -lt $MaxConnectRetries; $i++) {
        try {
            Invoke-WebRequest -Uri "http://localhost:$HostPort" | Out-Null
            Write-Host -ForegroundColor Green 'Container is up'
            break
        }
        catch {
            if ($i -lt ($MaxConnectRetries - 1)) {
                Start-Sleep -Seconds 3
                Write-Host -ForegroundColor DarkGray "Attempt: $($i + 2)"
            }
            else {
                Write-Error 'Container did not come up'
            }
        }
    }

    Write-Host
    Write-Host -ForegroundColor Cyan 'Installing WordPress...'

    $containerId = & docker-compose --project-name $ProjectName ps -q wordpress
    if (-Not $?) {
        throw 'Could not determine container id of wordpress container'
    }

    # NOTE: For CLI commands, the PHP version doesn't really matter. Thus we don't use it.
    & docker run -it --rm --volumes-from $containerId --network container:$containerId wordpress:cli core install `
        "--url=localhost:$HostPort" `
        '--title=Wordpress Test Site' `
        --admin_user=admin `
        --admin_password=test1234 `
        '--admin_email=test@test.com' `
        --skip-email `
        --color
    if (-Not $?) {
        throw 'Could not install Wordpress'
    }

    Write-Host
    Write-Host -ForegroundColor Cyan 'Activating plugin "BlogText"...'
    & docker run -it --rm --volumes-from $containerId --network container:$containerId wordpress:cli plugin activate blogtext
    if (-Not $?) {
        throw 'Could not activate the BlogText plugin'
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
