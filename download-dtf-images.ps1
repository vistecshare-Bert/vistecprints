# Download garment images for the Custom DTF Print catalog
# Run once, then upload images/dtf/ folder to HostGator

$outDir = "$PSScriptRoot\images\dtf"
New-Item -ItemType Directory -Force $outDir | Out-Null

$headers = @{
    "User-Agent" = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
    "Accept"     = "image/avif,image/webp,image/apng,image/*,*/*;q=0.8"
    "Referer"    = "https://www.google.com/"
}

$images = @(
    @{ file = "bc3001.jpg";      url = "https://www.bellacanvas.com/bella/product/hires/30010024411_1.jpg" },
    @{ file = "bc3001cvc.jpg";   url = "https://www.bellacanvas.com/bella/product/hires/3001CVC0025411_1.jpg" },
    @{ file = "bc3001y.jpg";     url = "https://www.bellacanvas.com/bella/product/hires/3001Y0024411_1.jpg" },
    @{ file = "bc3001ycvc.jpg";  url = "https://www.bellacanvas.com/bella/product/hires/3001YCVC0025411_1.jpg" },
    @{ file = "nl3600.jpg";      url = "https://www.nextlevelapparel.com/cdn/shop/files/Web1000x1000-PhotoModel-3600Men_sCottonT-Shirt-Front-BlueJean.png?width=600" },
    @{ file = "nl3310.jpg";      url = "https://www.nextlevelapparel.com/cdn/shop/files/Web1000x1000-PhotoModel-YouthCottonT-Shirt3310Boys-Front-Maroon.png?width=600" },
    @{ file = "nl6210.jpg";      url = "https://www.nextlevelapparel.com/cdn/shop/files/Web1000x1000-PhotoModel-6210Men_sCVCT-Shirt-Front-HeatherSlateBlue.png?width=600" },
    @{ file = "nl3900.jpg";      url = "https://www.nextlevelapparel.com/cdn/shop/files/Web1000x1000-PhotoModel-3900Women_sCottonT-Shirt-Front-LightBlue.png?width=600" },
    @{ file = "nl6200.jpg";      url = "https://www.nextlevelapparel.com/cdn/shop/files/Web1000x1000-PhotoModel-6200Men_sFestivalT-Shirt-Front-Lavender.png?width=600" },
    @{ file = "nl6211.jpg";      url = "https://www.nextlevelapparel.com/cdn/shop/files/6211_Sand_Mens_F_M-1507.jpg?width=600" },
    @{ file = "gildan_g5000.jpg";  url = "https://www.gildan.com/on/demandware.static/-/Sites-gildan-master-catalog/default/dw8f00a2b5/product-images/G5000/G5000_BLK_PFQT.jpg" },
    @{ file = "gildan_g5000l.jpg"; url = "https://www.gildan.com/on/demandware.static/-/Sites-gildan-master-catalog/default/dw8f00a2b5/product-images/G5000L/G5000L_BLK_PFQT.jpg" },
    @{ file = "gildan_g5000b.jpg"; url = "https://www.gildan.com/on/demandware.static/-/Sites-gildan-master-catalog/default/dw8f00a2b5/product-images/G5000B/G5000B_BLK_PFQT.jpg" },
    @{ file = "gildan_64000.jpg";  url = "https://www.gildan.com/on/demandware.static/-/Sites-gildan-master-catalog/default/dw8f00a2b5/product-images/64000/64000_BLK_PFQT.jpg" },
    @{ file = "gildan_64000b.jpg"; url = "https://www.gildan.com/on/demandware.static/-/Sites-gildan-master-catalog/default/dw8f00a2b5/product-images/64000B/64000B_BLK_PFQT.jpg" },
    @{ file = "gildan_12500.jpg";  url = "https://www.gildan.com/on/demandware.static/-/Sites-gildan-master-catalog/default/dw8f00a2b5/product-images/12500/12500_BLK_PFQT.jpg" },
    @{ file = "jerzees_29lsr.jpg"; url = "https://www.jerzees.com/dw/image/v2/BDGK_PRD/on/demandware.static/-/Sites-jerzees-master-catalog/default/dw4c5a0d25/images/large/29LSR_Black_Front.jpg" }
)

$ok = 0
$fail = 0

foreach ($img in $images) {
    $dest = Join-Path $outDir $img.file
    if (Test-Path $dest) {
        Write-Host "  SKIP $($img.file) (already exists)"
        $ok++
        continue
    }
    try {
        Invoke-WebRequest -Uri $img.url -OutFile $dest -Headers $headers -TimeoutSec 20 -ErrorAction Stop
        $size = (Get-Item $dest).Length
        if ($size -lt 2000) {
            Remove-Item $dest -Force
            Write-Host "  FAIL $($img.file) - server returned error page" -ForegroundColor Yellow
            $fail++
        } else {
            $kb = [math]::Round($size / 1024, 1)
            Write-Host "  OK   $($img.file) ($kb KB)"
            $ok++
        }
    } catch {
        Write-Host "  FAIL $($img.file): $($_.Exception.Message)" -ForegroundColor Yellow
        $fail++
    }
}

Write-Host ""
Write-Host "Done: $ok downloaded, $fail failed." -ForegroundColor Cyan
if ($fail -gt 0) {
    Write-Host "For failed images, log in to sanmar.com and download manually to images\dtf\" -ForegroundColor Yellow
}
