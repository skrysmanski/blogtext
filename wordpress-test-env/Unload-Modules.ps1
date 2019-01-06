#
# Unloads all PowerShell script modules from the specified directory.
#
# Call it like this:
#
#    & $PSScriptRoot/Unload-Modules.ps1
#
# The main purpose of this script is to allow modules to be reloaded when they have changed.
#
# Reason: By default, "Import-Module" doesn't reload a module if it's already loaded - even if
# its source code has changed.
#
# One can use "Import-Module ... -Force" but this has three downsides:
#
#   1. You need to make sure that -Force is really used in every "Import-Module" statement (or things may get weird)
#   2. "-Force" clears all variables with "$script:xxx" scope. This is problematic when a function force imports
#      a module after this module has already been used (and thus set some script variables).
#   3. With "-Force", if the same module is imported by multiple scripts/modules within the same "process", it gets
#      reimported multiple times (increasing the startup time slightly).
#
# Thus, it's easier to just unload all modules and then use "Import-Module" without "-Force".
#
Param(
	[string] $ModulePath = ''
)

# Stop on every error
$script:ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ModulePath)) {
	$ModulePath = $PSScriptRoot
}
else {
	$ModulePath = Resolve-Path $ModulePath
}

$modulesToRemove = @()
$loadedModules = Get-Module -All
foreach ($loadedModule in $loadedModules) {
	if ($loadedModule.ModuleType -ne 'Script') {
		continue
	}

	if ($loadedModule.Path.StartsWith($ModulePath, [System.StringComparison]::OrdinalIgnoreCase)) {
		$modulesToRemove += $loadedModule
	}
}

$modulesToRemove | Remove-Module -Force
