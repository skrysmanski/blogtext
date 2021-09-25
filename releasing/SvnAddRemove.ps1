#!/usr/bin/env pwsh
#
# Adds and removes files from Subversion.
#
param(
    [Parameter(Mandatory=$True)]
    [string] $WorkingDirectory
)

# Stop on every error
$script:ErrorActionPreference = 'Stop'

try {
    Push-Location $WorkingDirectory

    # Untrack all files that are deleted from the working copy
    # See: https://stackoverflow.com/a/9628914/614177
    & svn status | Where-Object { $_ -match '^!\s+(.*)' } | ForEach-Object { & svn rm $Matches[1] }

    # Track all currently untracked files
    # See: https://stackoverflow.com/a/4046862/614177
    # NOTE: "--force" suppresses warning when adding files that are already tracked
    & svn add * --auto-props --parents --depth infinity -q --force
}
finally {
    Pop-Location
}
