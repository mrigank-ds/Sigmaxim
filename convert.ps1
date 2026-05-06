$inputFile = "E:\xampp\htdocs\Sigmaxim10\sdsweb-d10.sql"
$outputFile = "E:\xampp\htdocs\Sigmaxim10\sdsweb-d10-mysql.sql"

$content = Get-Content $inputFile -Raw

# Replace all USE statements with your MySQL target database
$targetDatabase = "sdsweb_d10"
$content = $content -replace "(?im)^\s*USE\s+.*?;", "USE `$targetDatabase`;"

# Remove GO statements
$content = $content -replace "(?im)^\s*GO\s*$", ""

# Remove SQL Server settings
$content = $content -replace "(?im)^\s*SET\s+ANSI_NULLS.*?$", ""
$content = $content -replace "(?im)^\s*SET\s+QUOTED_IDENTIFIER.*?$", ""

# Replace data types
$content = $content -replace "NVARCHAR", "VARCHAR"
$content = $content -replace "NCHAR", "CHAR"
$content = $content -replace "DATETIME2", "DATETIME"
$content = $content -replace "BIT", "TINYINT(1)"

# Replace IDENTITY with AUTO_INCREMENT
$content = $content -replace "IDENTITY\s*\(\s*\d+\s*,\s*\d+\s*\)", "AUTO_INCREMENT"

# Remove dbo schema
$content = $content -replace "\[dbo\]\.", ""

# Replace square brackets with backticks
$content = $content -replace "\[", [char]96
$content = $content -replace "\]", [char]96

# Remove SQL Server CREATE USER statements
$content = $content -replace "(?is)CREATE\s+USER\s+`?\w+`?\s+FOR\s+LOGIN\s+`?\w+`?.*?;", ""

Set-Content -Path $outputFile -Value $content

Write-Host "Conversion complete! Output saved to $outputFile"
