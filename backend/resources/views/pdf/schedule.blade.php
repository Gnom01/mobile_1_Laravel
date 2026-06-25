<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8"/>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #000; }
    .page { padding: 15mm 14mm; }

    .logo-wrap { text-align: center; margin-bottom: 14pt; }
    .logo-wrap img { width: 150pt; }

    h1 { font-size: 16pt; font-weight: bold; text-align: center; margin-bottom: 6pt; }
    h2 { font-size: 13pt; font-weight: bold; text-align: center; margin-bottom: 16pt; }

    .section-label { font-size: 9.5pt; font-weight: bold; margin-top: 10pt; margin-bottom: 4pt; }

    .inst-table { width: 100%; border-collapse: collapse; margin: 6pt 0; font-size: 9pt; }
    .inst-table th { background-color: #eee; border: 1px solid #aaa; padding: 4pt 5pt; font-weight: bold; text-align: center; }
    .inst-table td { border: 1px solid #aaa; padding: 3pt 5pt; text-align: center; }

    .footnote { font-size: 7.5pt; font-style: italic; margin-top: 6pt; }

    .sig-table { width: 100%; border-collapse: collapse; margin-top: 24pt; }
    .sig-table td { width: 50%; text-align: center; font-size: 9.5pt; }
    .sig-line { border-top: 1px solid #000; width: 70%; margin: 0 auto 4pt; }
</style>
</head>
<body>
<div class="page">

    <div class="logo-wrap">
        <img src="{{ $data['logo'] }}" alt="EDS Logo"/>
    </div>

    <h1>Wzór harmonogramu do umowy</h1>
    <h2>{{ $data['contractSignature'] }}</h2>

    <p class="section-label">Wykaz płatności w poszczególnych miesiącach*:</p>

    <table class="inst-table">
        <thead>
            <tr>
                <th>Lp</th>
                <th>Kwota bazowa</th>
                <th>Rabat %</th>
                <th>Rabat kwotowy</th>
                <th>Po rabacie</th>
                <th>Miesiąc</th>
                <th>Termin płatności</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['rawInstallments'] as $row)
            <tr>
                <td>{{ $row['nr'] }}</td>
                <td>{{ $row['basePrice'] }}</td>
                <td>{{ $row['discountProcent'] }}</td>
                <td>{{ $row['discountCash'] }}</td>
                <td>{{ $row['priceAfterDiscount'] }}</td>
                <td>{{ $row['paymentMonth'] }}</td>
                <td>{{ $row['paymentDate'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="footnote">
        * Harmonogram zniżek został wygenerowany przy założeniu że Uczestnik będzie spełniał warunki promocji
        „{{ $data['discountName'] }}" do końca trwania Umowy
    </p>

    <table class="sig-table">
        <tr>
            <td>
                <br/><br/>
                <div class="sig-line"></div>
                Data i podpis w imieniu EDS
            </td>
            <td>
                <br/><br/>
                <div class="sig-line"></div>
                Data i podpis Klienta
            </td>
        </tr>
    </table>

</div>
</body>
</html>
