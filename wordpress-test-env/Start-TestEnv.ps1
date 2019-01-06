#!/usr/bin/env pwsh
param(
    [Parameter(Mandatory=$True)]
    [string] $ProjectFile,

    [string] $WordpressVersion = '',

    [string] $PhpVersion = '',

    [int] $Port = 8080,

    [int] $MaxConnectRetries = 20
)

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    & $PSScriptRoot/Unload-Modules.ps1

    Import-Module "$PSScriptRoot/WordpressTestEnv.psm1" -DisableNameChecking

    $projectDescriptor = Get-ProjectDescriptor $ProjectFile

    $wordpressTag = Get-DockerWordpressTag -WordpressVersion $WordpressVersion -PhpVersion $PhpVersion

    $composeProjectName = Get-DockerComposeProjectName -ProjectName $projectDescriptor.ProjectName -WordpressTag $wordpressTag

    $volumes = @()

    if ($projectDescriptor.Mounts) {
        foreach ($mount in $projectDescriptor.Mounts) {
            $hostPath = [IO.Path]::GetFullPath($mount.Host)
            $volumeString = "$($hostPath):/var/www/html/$($mount.Container)"
            if ($mount.ReadOnly) {
                $volumeString += ':ro'
            }

            $volumes += $volumeString
        }
    }

    $composeFilePath = New-WordpressTestEnvComposeFile `
        -ComposeProjectName $composeProjectName `
        -WordpressTag $wordpressTag `
        -Port $Port `
        -Volumes $volumes

    & docker-compose --file $composeFilePath --project-name $composeProjectName up --detach
    if (-Not $?) {
        throw '"docker-compose up" failed'
    }

    Write-Host
    Write-Host 'Waiting for containers to come up...'

    for ($i = 0; $i -lt $MaxConnectRetries; $i++) {
        try {
            Invoke-WebRequest -Uri "http://localhost:$Port" | Out-Null
            Write-Host -ForegroundColor Green 'Container is up'
            break
        }
        catch {
            if ($i -lt ($MaxConnectRetries - 1)) {
                Start-Sleep -Seconds 3
                Write-Host -ForegroundColor DarkGray "Attempt: $($i + 2)"
            }
            else {
                Write-Error 'Containers did not come up'
            }
        }
    }

    $containerId = & docker-compose --file $composeFilePath --project-name $composeProjectName ps -q web
    if ((-Not $?) -or (-Not $containerId)) {
        throw 'Could not determine container id of web container'
    }

    # Fix some permissions that are broken due to mounting the plugin.
    & docker exec -t $containerId chown www-data /var/www/html/wp-content /var/www/html/wp-content/plugins
    if (-Not $?) {
        throw 'Could change ownership of certain directories in the container.'
    }

    function Invoke-WordpressCli {
        #
        # Wordpress CLI:
        #  - https://wp-cli.org/
        #  - https://developer.wordpress.org/cli/commands/
        #
        # NOTE: For CLI commands, the PHP version doesn't really matter. Thus we don't use it.
        #
        # IMPORTANT: We need to specify the user id (33) explicitely here because in the CLI image the user id
        #   for www-data is different than in the actual wordpress image (most likely because the cli image is
        #   Alpine while the actual image is Debian). See also: https://github.com/docker-library/wordpress/issues/256
        & docker run -it --rm --user 33 --volumes-from $containerId --network container:$containerId wordpress:cli @args
        if (-Not $?) {
            Write-Error "Wordpress CLI failed: $args"
        }
    }

    Write-Host
    Write-Host -ForegroundColor Cyan 'Installing WordPress...'
    Invoke-WordpressCli core install `
        "--url=localhost:$Port" `
        '--title=Wordpress Test Site' `
        --admin_user=admin `
        --admin_password=test1234 `
        '--admin_email=test@test.com' `
        --skip-email `
        --color

    if ($projectDescriptor.SetupCommands) {
        foreach ($setupCommand in $projectDescriptor.SetupCommands) {
            if ($setupCommand.Condition) {
                $conditionMet = Invoke-Expression $setupCommand.Condition
                if (-Not $conditionMet) {
                    continue
                }
            }

            Write-Host
            Write-Host -ForegroundColor Cyan $setupCommand.Title

            $commandArgs = $setupCommand.CommandArgs
            Invoke-WordpressCli @commandArgs
        }
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
