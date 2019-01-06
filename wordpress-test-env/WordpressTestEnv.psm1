# Stop on every error
$script:ErrorActionPreference = 'Stop'

function Get-ProjectDescriptor([string] $ProjectFile) {
    if ([string]::IsNullOrWhiteSpace($ProjectFile)) {
        Write-Error 'No project file has been specified.'
    }

    $projectDescriptor = Get-Content $ProjectFile -Encoding 'utf8' | ConvertFrom-Json

    if ([string]::IsNullOrWhiteSpace($projectDescriptor.ProjectName)) {
        Write-Error "Missing property 'ProjectName' in '$ProjectFile'."
    }

    return $projectDescriptor
}

function Get-DockerWordpressTag([string] $WordpressVersion, [string] $PhpVersion) {
    if (($WordpressVersion -ne '') -and ($PhpVersion -ne '')) {
        return "$WordpressVersion-php$PhpVersion"
    }
    elseif ($WordpressVersion -ne '') {
        return $WordpressVersion
    }
    elseif ($PhpVersion -ne '') {
        return "php$PhpVersion"
    }
    else {
        return 'latest'
    }
}

function Get-DockerComposeProjectName([string] $ProjectName, [string] $WordpressTag) {
    return "$ProjectName-wp-$WordpressTag"
}

function Get-ComposeFilePath([string] $ComposeProjectName) {
    New-Item "$PSScriptRoot/envs" -ItemType Directory -ErrorAction SilentlyContinue | Out-Null
    return "$PSScriptRoot/envs/docker-compose.$($ComposeProjectName).yml"
}

function New-WordpressTestEnvComposeFile([string] $ComposeProjectName, [string] $WordpressTag, [int] $Port, [string[]] $volumes) {
    $DB_NAME = 'wpdb'
    $DB_USER = 'wordpress'
    $DB_PASSWORD = 'insecure-password123'

    $volumesString = ''

    if ($volumes) {
        foreach ($volume in $volumes) {
            $volumesString += @"
            - $volume`n
"@
        }

        $volumesString = $volumesString.TrimEnd("`n")
    }

    $contents = @"
version: '3.1'

services:

    web:
        image: wordpress:$WordpressTag
        container_name: $($ComposeProjectName)_web
        depends_on:
            - db
        ports:
            - $($Port):80
        volumes:
            - wordpress:/var/www/html
$volumesString
        environment:
            WORDPRESS_DB_HOST: db
            WORDPRESS_DB_NAME: $DB_NAME
            WORDPRESS_DB_USER: $DB_USER
            WORDPRESS_DB_PASSWORD: $DB_PASSWORD
            WORDPRESS_DEBUG: '1'

    db:
        image: mysql:5.7
        container_name: $($ComposeProjectName)_db
        volumes:
            - db:/var/lib/mysql
        environment:
            MYSQL_DATABASE: $DB_NAME
            MYSQL_USER: $DB_USER
            MYSQL_PASSWORD: $DB_PASSWORD
            MYSQL_RANDOM_ROOT_PASSWORD: '1'

volumes:
    wordpress:
    db:
"@

    $composeFilePath = Get-ComposeFilePAth -ComposeProjectName $ComposeProjectName
    $contents | Out-File $composeFilePath -Encoding 'utf8'

    return $composeFilePath
}
