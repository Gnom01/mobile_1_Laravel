<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8"/>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #000; }

    .page { padding: 15mm 12mm; }

    /* header */
    .header-row { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
    .header-logo img { width: 140pt; }
    .header-title { vertical-align: middle; padding-left: 12pt; font-size: 9pt; }
    .header-title .contract-number { font-size: 12pt; font-weight: bold; display: block; }

    /* sections */
    .section-title { font-size: 9pt; font-weight: bold; margin-top: 8pt; margin-bottom: 3pt; }
    .divider { border-top: 1px solid #000; margin: 4pt 0; font-size: 5pt; }

    /* two-column layout using table */
    .two-col { width: 100%; border-collapse: collapse; margin-bottom: 2pt; }
    .two-col td { width: 50%; vertical-align: top; padding: 1pt 0; font-size: 9pt; }
    .label { font-weight: normal; }
    .value { font-weight: bold; }

    /* installment table */
    .inst-table { width: 100%; border-collapse: collapse; margin: 6pt 0; font-size: 8pt; }
    .inst-table th { background-color: #eee; border: 1px solid #aaa; padding: 3pt 4pt; font-weight: bold; text-align: center; }
    .inst-table td { border: 1px solid #aaa; padding: 2pt 4pt; text-align: center; }

    /* legal text */
    .legal { font-size: 8.5pt; margin-top: 3pt; margin-bottom: 2pt; text-align: justify; }
    .legal-indent { font-size: 8.5pt; margin-top: 2pt; margin-left: 16pt; text-align: justify; }
    .legal-indent2 { font-size: 8.5pt; margin-top: 2pt; margin-left: 28pt; text-align: justify; }

    /* signature */
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 10pt; }
    .sig-table td { width: 50%; text-align: center; font-size: 9pt; padding-top: 16pt; }
    .sig-line { border-top: 1px solid #000; width: 70%; margin: 0 auto 3pt; }
    .consent-row { margin-top: 8pt; font-size: 8.5pt; }

    .footer { text-align: right; font-size: 8pt; margin-top: 6pt; }
</style>
</head>
<body>
<div class="page">

{{-- ── HEADER ───────────────────────────────────────────────── --}}
<table class="header-row">
    <tr>
        <td class="header-logo" style="width:155pt;">
            <img src="{{ $data['logo'] }}" alt="EDS Logo" />
        </td>
        <td class="header-title">
            UMOWA O UCZESTNICTWO W ZAJĘCIACH GRUPOWYCH NR:
            <span class="contract-number">{{ $data['contractSignature'] }}</span>
        </td>
    </tr>
</table>

<p class="legal">
    zawarta w dniu {{ $data['contractDate'] }} w {{ $data['contractLocation'] }} pomiędzy:
    {{ $data['companyName'] }}, zwanym dalej EDS, a Klientem:
</p>
<div class="divider">&nbsp;</div>

{{-- ── DANE OSOBOWE KLIENTA ─────────────────────────────────── --}}
<p class="section-title">DANE OSOBOWE KLIENTA:</p>
<table class="two-col">
    <tr>
        <td>
            <span class="label">Imię i nazwisko: </span>
            <span class="value">{{ $data['payerUserData']['firstName'] }} {{ $data['payerUserData']['lastName'] }}</span>
        </td>
        <td>
            @if($data['payerUserData']['pesel'])
            <span class="label">PESEL: </span>
            <span class="value">{{ $data['payerUserData']['pesel'] }}</span><br/>
            @endif
            <span class="label">Telefon: </span>
            <span class="value">{{ $data['payerUserData']['phone'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Adres: </span>
            <span class="value">
                {{ $data['payerUserData']['postalCode'] }} {{ $data['payerUserData']['city'] }},
                {{ $data['payerUserData']['street'] }} {{ $data['payerUserData']['building'] }}
                @if($data['payerUserData']['flat'])/{{ $data['payerUserData']['flat'] }}@endif
            </span>
        </td>
        <td></td>
    </tr>
    <tr>
        <td>
            <span class="label">E-mail: </span>
            <span class="value">{{ $data['payerUserData']['email'] }}</span>
        </td>
        <td></td>
    </tr>
</table>

{{-- ── DANE UCZESTNIKA ──────────────────────────────────────── --}}
<p class="section-title">DANE UCZESTNIKA (jeśli inne niż dane osobowe Klienta, lub Uczestnik jest osobą małoletnią):</p>
@php $p = $data['participantUser']; @endphp
<table class="two-col">
    <tr>
        <td>
            <span class="label">Imię i nazwisko: </span>
            <span class="value">
                @if($p && $p['firstName'] && $p['lastName'])
                    {{ $p['firstName'] }} {{ $p['lastName'] }}
                @else -
                @endif
            </span>
        </td>
        <td>
            @if($p && $p['pesel'])
            <span class="label">PESEL: </span><span class="value">{{ $p['pesel'] }}</span><br/>
            @endif
            @if($p && $p['dateOfBirth'])
            <span class="label">Data urodzenia: </span><span class="value">{{ $p['dateOfBirth'] }}</span><br/>
            @endif
            @if($p && $p['phone'])
            <span class="label">Telefon: </span><span class="value">{{ $p['phone'] }}</span>
            @endif
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Adres: </span>
            <span class="value">
                @if($p && $p['postalCode'] && $p['city'] && $p['street'] && $p['building'])
                    {{ $p['postalCode'] }} {{ $p['city'] }}, {{ $p['street'] }} {{ $p['building'] }}
                    @if($p['flat'])/{{ $p['flat'] }}@endif
                @else -
                @endif
            </span>
        </td>
        <td></td>
    </tr>
    <tr>
        <td>
            <span class="label">E-mail: </span>
            <span class="value">{{ ($p && $p['email']) ? $p['email'] : '-' }}</span>
        </td>
        <td></td>
    </tr>
</table>
<div class="divider">&nbsp;</div>

{{-- ── SZCZEGÓŁY UMOWY ─────────────────────────────────────── --}}
<p class="section-title">SZCZEGÓŁY UMOWY:</p>
<table class="two-col">
    <tr>
        <td>
            <span class="label">Rodzaj umowy: </span>
            <span class="value">{{ $data['groupData']['contractType'] }}</span>
        </td>
        <td>
            <span class="label">Data rozpoczęcia Umowy: </span>
            <span class="value">{{ $data['contractStartDate'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Symbol (nazwa grupy): </span>
            <span class="value">{{ $data['courseData']['courseHeadingName'] }}</span>
        </td>
        <td>
            <span class="label">Data zakończenia Umowy: </span>
            <span class="value">{{ $data['contractEndDate'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Ilość zajęć w tygodniu: </span>
            <span class="value">{{ $data['courseData']['courseFrequency'] }}</span>
        </td>
        <td>
            <span class="label">Długość zajęć: </span>
            <span class="value">{{ $data['courseData']['courseDuration'] }} min</span>
        </td>
    </tr>
</table>
<div class="divider">&nbsp;</div>

{{-- ── SZCZEGÓŁY PŁATNOŚCI ─────────────────────────────────── --}}
<p class="section-title">SZCZEGÓŁY PŁATNOŚCI:</p>
<table class="two-col">
    <tr>
        <td>
            <span class="label">Koszt kursu: </span>
            <span class="value">{{ number_format($data['coursePrice'], 2, ',', ' ') }} zł</span>
        </td>
        <td>
            @if($data['entryFee'] > 0)
            <span class="label">Opłata wpisowa: </span>
            <span class="value">{{ number_format($data['entryFee'], 2, ',', ' ') }} zł</span>
            @endif
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Płatność: </span>
            <span class="value">{{ $data['groupData']['periodOfPayment'] }}</span>
        </td>
        <td>
            <span class="label">Wartość pro-raty (bazowa): </span>
            <span class="value">{{ number_format($data['payZero']['installmentZero'], 2, ',', ' ') }} zł</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Liczba pełnych rat: </span>
            <span class="value">{{ $data['numberOfInstallments'] }}</span>
        </td>
        <td>
            @if($data['payZero']['discountCashZero'] > 0)
            <span class="label">Rabat pro-raty: </span>
            <span class="value" style="color:#cc0033;">- {{ number_format($data['payZero']['discountCashZero'], 2, ',', ' ') }} zł</span><br/>
            <span class="label">Pro-rata po rabacie: </span>
            <span class="value">{{ number_format($data['payZero']['installmentZeroAfterDiscount'], 2, ',', ' ') }} zł</span><br/>
            @endif
            <span class="label">Suma opłat początkowych: </span>
            <span class="value">{{ number_format($data['payZero']['amountZero'], 2, ',', ' ') }} zł</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Wartość rat miesięcznych: </span>
            <span class="value">{{ number_format($data['monthlyInstallment'], 2, ',', ' ') }} zł</span>
        </td>
        <td></td>
    </tr>
</table>

{{-- ── INSTALLMENT TABLE ────────────────────────────────────── --}}
<table class="inst-table">
    <thead>
        <tr>
            <th>Lp</th>
            <th>Kwota bazowa</th>
            <th>Rabat</th>
            <th>Do zapłaty</th>
            <th>Termin płatności</th>
            <th>Miesiąc</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['installments'] as $row)
        <tr>
            <td>{{ $row['nr'] }}</td>
            <td>{{ $row['basePrice'] }}</td>
            <td>{{ $row['discount'] }}</td>
            <td>{{ $row['priceAfterDiscount'] }}</td>
            <td>{{ $row['paymentDate'] }}</td>
            <td>{{ $row['month'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="divider">&nbsp;</div>

{{-- ── SECTION 1 ────────────────────────────────────────────── --}}
<p class="section-title">1. POSTANOWIENIA OGÓLNE</p>
<p class="legal">1.1. Częścią niniejszej Umowy jest Regulamin oraz Statut EDS, które są dostępne w placówce EDS. Regulamin i Statut stanowią załącznik nr 1 do niniejszej umowy. W przypadku zmiany Regulaminu lub Statutu EDS z ważnych przyczyn Klient otrzyma nową wersję dokumentu na wskazany przez siebie adres e-mail z co najmniej 14-dniowym wyprzedzeniem z prawem do rozwiązania Umowy z dniem poprzedzającym dzień wejścia w życie nowego brzmienia Regulaminu lub Statutu EDS.</p>
<p class="legal">1.2. Wszelkie pojęcia zawarte w niniejszej Umowie mają znaczenie nadane im przez Regulamin.</p>
<p class="legal">1.3. Klient oświadcza, że nie istnieją żadne przeciwwskazania, w tym zdrowotne, do uczestnictwa w zajęciach Grupy będących przedmiotem niniejszej Umowy.</p>
<p class="legal">1.4. W przypadku, gdy Uczestnikiem jest osoba małoletnia, Klient oświadcza, iż jest rodzicem lub opiekunem prawnym małoletniego Uczestnika i w związku z tym ma pełne prawo do zawarcia niniejszej umowy w jego imieniu. Klient oświadcza, że nie istnieją żadne przeciwwskazania, w tym zdrowotne, do uczestnictwa Uczestnika w zajęciach Grupy będących przedmiotem niniejszej Umowy.</p>

{{-- ── SECTION 2 ────────────────────────────────────────────── --}}
<p class="section-title">2. PRZEDMIOT UMOWY</p>
<p class="legal">2.1. EDS zobowiązuje się świadczyć na rzecz Uczestnika usługi nauki tańca oraz/lub nauki choreografii, zgodnie z celami i zadaniami zawartymi w Statucie, na zajęciach prowadzonych w Grupie, zgodnie z postanowieniami niniejszej Umowy. Usługi nauki tańca oraz/lub nauki choreografii będą prowadzone zgodnie z autorskim programem opracowanym przez EDS, z którym Uczestnik się zapoznał i który w każdej chwili jest dostępny do wglądu w siedzibie EDS.</p>
<p class="legal">2.2. Zajęcia Grupy, będące przedmiotem Umowy, są prowadzone zgodnie z harmonogramem dostępnym w placówce EDS, z wyłączeniem dni ustawowo wolnych od pracy, ferii zimowych oraz wakacji letnich. Harmonogram stanowi załącznik nr 2 do niniejszej umowy.</p>
<p class="legal">2.3. Za zgodą EDS, Uczestnik może zmienić Grupę w trakcie obowiązywania Umowy na inną z oferty Zajęć regularnych o tej samej długości i częstotliwości, pod warunkiem dostępności miejsca w danej Grupie. Po potwierdzeniu dostępności miejsca w innej Grupie Uczestnik zobowiązany jest złożyć w recepcji EDS osobiście podpisany formularz aktualizacji danych. Zmiana grupy nie wymaga zmiany umowy w trybie punktu 5.2.</p>
<p class="legal">2.4. Strony przewidują możliwość odrabiania Zajęć w ciągu 30 dni od daty Zajęć, w których Uczestnik nie mógł uczestniczyć, na innych Zajęciach z oferty otwartych Zajęć regularnych, o tej samej długości, pod warunkiem dostępności miejsca w danej Grupie i po uprzedniej akceptacji Managera EDS.</p>
<p class="legal">2.5. Klient oświadcza, iż w przypadku wprowadzenia przez władze stosownych przepisów regulujących reżim przeprowadzania zajęć objętych niniejszą umową w związku z obowiązywaniem stanu zagrożenia epidemicznego lub stanu epidemii na obszarze Polski, zobowiązuje się on osobiście lub w imieniu Uczestnika, którego jest opiekunem prawnym do każdorazowego podporządkowania się wprowadzonym w EDS procedurom związanym z tymi przepisami.</p>

{{-- ── SECTION 3 ────────────────────────────────────────────── --}}
<p class="section-title">3. CZAS TRWANIA UMOWY</p>
<p class="legal">3.1. Umowa zostaje zawarta na czas określony do dnia zakończenia umowy wskazanej w części wstępnej.</p>
<p class="legal">3.2. Klient ma prawo wypowiedzenia niniejszej Umowy w dowolnym momencie z zachowaniem dwumiesięcznego okresu wypowiedzenia, a w przypadku zajęć dla 3-latków oraz zajęć dla par - z zachowaniem jednomiesięcznego okresu wypowiedzenia, ze skutkiem na koniec miesiąca kalendarzowego. Oświadczenie o wypowiedzeniu należy złożyć w formie pisemnej pod rygorem nieważności osobiście w placówce EDS lub drogą pocztową (liczy się data stempla pocztowego). Umowa ulega rozwiązaniu po upływie ostatniego dnia wypowiedzenia.</p>
<p class="legal">3.3. Klientowi, który wypowiedział Umowę przysługuje w terminie 14 dni od dnia zakończenia Umowy zwrot uiszczonych, a niewykorzystanych opłat za Zajęcia pozostałe do końca okresu, do którego miała trwać Umowa.</p>
<p class="legal">3.4. EDS ma prawo rozwiązania niniejszej Umowy ze skutkiem natychmiastowym, w przypadku, gdy:</p>
<p class="legal-indent">· nie została wniesiona opłata za udział w zajęciach na warunkach określonych w Umowie,</p>
<p class="legal-indent">· mimo upomnienia Uczestnik nie stosuje się do obowiązujących regulacji wewnętrznych, w tym Regulaminu, właściwego Statutu, przepisów bhp i p-poż,</p>
<p class="legal-indent">· w toku zgłoszenia Klient lub Uczestnik utajnił problemy (w tym zdrowotne), które uniemożliwiają mu uczestnictwo w nauce tańca,</p>
<p class="legal-indent">· Uczestnik stosuje przemoc psychiczną lub fizyczną w stosunku do innych Uczestników zajęć lub Instruktora,</p>
<p class="legal-indent">· Uczestnik przebywa na terenie siedziby EDS lub w miejscu prowadzenia zajęć pod wpływem środków odurzających lub rozpowszechnia ww. środki na terenie siedziby EDS lub w miejscu prowadzenia zajęć,</p>
<p class="legal-indent">· Uczestnik dopuszcza się wandalizmu, niszczenia sprzętu na terenie siedziby EDS lub w miejscu prowadzenia zajęć,</p>
<p class="legal-indent">· poprzez swoje nieodpowiednie zachowanie Uczestnik uniemożliwia prowadzenia zajęć w Grupie,</p>
<p class="legal-indent">· Uczestnik swoim zachowaniem narusza zasady współżycia społecznego i dobrych obyczajów oraz nie podejmuje współpracy celem rozwiązania problemu.</p>
<p class="legal">3.5. W przypadku nie przestrzegania postanowień Umowy, Regulaminu lub Statutu przez Klienta lub Uczestnika (w przypadku, gdy Uczestnikiem jest osoba małoletnia), EDS może rozwiązać Umowę ze skutkiem natychmiastowym. Rozwiązanie powinno zawierać uzasadnienie.</p>
<p class="legal">3.6. Rozwiązanie umowy jest jednoznaczne ze skreśleniem Uczestnika z listy Uczestników EDS.</p>
<p class="legal">3.7. Od decyzji w przedmiocie skreślenia z listy Uczestników można wnieść odwołanie do organu prowadzącego placówkę EDS na zasadach i w trybie określonym w Statucie.</p>
<p class="legal">3.8. W zakresie rozliczeń związanych z rozwiązaniem Umowy stosuje się odpowiednio zasady określone w Regulaminie.</p>
<p class="legal">3.9. Faktyczne zaprzestanie uczestnictwa w Zajęciach nie jest równoczesne rozwiązaniem niniejszej Umowy. Klient ma obowiązek uiszczania płatności za Zajęcia, które odbyły się do chwili rozwiązania Umowy.</p>
<p class="legal">3.10. Nieobecność na Zajęciach nie zwalnia z obowiązku uiszczenia opłaty.</p>
<p class="legal">3.11. Klient oświadcza, że posiada i będzie posiadał środki finansowe na pokrycie należności określonych w niniejszej Umowie i że będzie regulował je terminowo.</p>

{{-- ── SECTION 4 ────────────────────────────────────────────── --}}
<p class="section-title">4. OPŁATY</p>
<p class="legal">4.1. Klient zobowiązuje się do opłacania rat oraz innych opłat w wysokości i terminach określonych w niniejszej Umowie.</p>
<p class="legal">4.2. Jednorazową opłatę wpisową oraz pro ratę należy uiścić przed rozpoczęciem Zajęć.</p>
<p class="legal">4.3. Klient może wybrać jeden z następujących sposobów uiszczenia opłat:</p>
<p class="legal-indent">a) przedpłatą za całość kursu, w terminie do 5 dnia miesiąca, w którym zaczynają się Zajęcia Grupy, jednak nie później niż w dniu rozpoczęcia Zajęć Grupy,</p>
<p class="legal-indent">b) w formie miesięcznych rat płatnych do 5 dnia każdego kolejnego miesiąca obowiązywania umowy począwszy od pierwszego miesiąca, w którym mają mieć miejsce Zajęcia Grupy, jednak nie później niż w dniu rozpoczęcia Zajęć Grupy w danym miesiącu. Sposób uiszczenia opłat Klient określa najpóźniej z chwilą dokonania pierwszej płatności.</p>
<p class="legal">4.4. Pro-rata jest ustalana w wysokości proporcjonalnej do ilości Zajęć Grupy w pierwszym miesiącu kalendarzowym trwania umowy.</p>
<p class="legal">4.5. Terminowe dokonanie płatności jest warunkiem uczestnictwa w Zajęciach Grupy.</p>
<p class="legal">4.6. Wysokość raty wyliczana jest na podstawie całkowitej wartości kursu oraz miesięcy pozostających do jego zakończenia. Jest ona stała przez cały okres umowy, niezależnie od ilości Zajęć Grupy w danym miesiącu.</p>
<p class="legal">4.7. Dniem dokonania płatności jest data uznania rachunku bankowego EDS.</p>
<p class="legal">4.8. Płatności przelewem należy wykonywać na rachunek bankowy: {{ $data['bankAccountNumber'] }}. W tytule należy podać imię i nazwisko Uczestnika oraz symbol Grupy, za które opłata jest wnoszona.</p>
<p class="legal">4.9. Szczegółowe formy płatności akceptowane przez EDS określone są w Regulaminie.</p>
<p class="legal">4.10. W okresie wypowiedzenia Klient ma obowiązek regulowania wszystkich należnych rat oraz opłat zgodnie z postanowieniami niniejszej umowy.</p>

{{-- ── SECTION 5 ────────────────────────────────────────────── --}}
<p class="section-title">5. POSTANOWIENIA KOŃCOWE</p>
<p class="legal">5.1 Klient oświadcza, że żąda od EDS rozpoczęcia świadczenia usług zgodnie z niniejszą Umową przed terminem przysługującego Klientowi prawa do odstąpienia od Umowy. Klient oświadcza, że został poinformowany przez EDS o treści art. 35 Ustawy z dnia 30 maja 2014 r. o prawach konsumenta (t.j. Dz. U. z 2020 r., poz. 287 ze zm.), zgodnie z którym Klient zobowiązany jest do zapłaty za świadczenia spełnione do chwili odstąpienia od umowy, a kwota obliczona zostanie proporcjonalnie do zakresu spełnionego świadczenia.</p>
<p class="legal">5.2 Niniejsza Umowa została sporządzona w dwóch, jednobrzmiących egzemplarzach, po jednym dla każdej ze Stron.</p>
<p class="legal">5.3 Zmiana niniejszej Umowy wymaga zachowania formy pisemnej pod rygorem nieważności.</p>
<p class="legal">5.4 W sprawach nieuregulowanych w niniejszej umowie stosuje się przepisy powszechnie obowiązujące.</p>
<p class="legal">5.5 Wszelkie spory związane z niniejszą umową będą rozstrzygane przez Sąd właściwy dla siedziby EDS.</p>
<p class="legal">5.6 Umowa zostaje zawarta z dniem podpisania przez obie Strony.</p>
<p class="legal">5.7 Załączniki:</p>
<p class="legal-indent">- Załącznik nr 1 - Regulamin i Statut EDS</p>
<p class="legal-indent">- Załącznik nr 2 - Harmonogram</p>

{{-- ── SECTION 6 RODO ───────────────────────────────────────── --}}
<p class="section-title">6. KLAUZULA INFORMACYJNA RODO</p>
<p class="legal">6.1. Administratorem Państwa danych osobowych w rozumieniu RODO jest EGURROLA DANCE STUDIO - Agustin Marek Egurrola z siedzibą w Warszawie, adres: ul. Żwirki i Wigury 99a, 02-089 Warszawa, NIP: 5251340026, REGON: 016098446.</p>
<p class="legal">6.2. W sprawach dotyczących przetwarzania Państwa danych osobowych można skontaktować się na adres korespondencyjny Administratora wskazany powyżej lub za pośrednictwem adresu e-mail: ido@egurrola.pl</p>
<p class="legal">6.3. Twoje dane osobowe mogą być przetwarzane przez Administratora na podstawie:</p>
<p class="legal-indent">· niezbędności do zawarcia i wykonania umowy lub do podjęcia działań przed jej zawarciem (art. 6 ust. 1 lit. b RODO) oraz umożliwienia Tobie zapisu na newslettery oferowane przez Administratora;</p>
<p class="legal-indent">· w celu wypełnienia obowiązku prawnego ciążącego na Administratorze (art. 6 ust. 1 lit. c RODO);</p>
<p class="legal-indent">· prawnie uzasadnionego interesu Administratora (art. 6 ust. 1 lit. f RODO) w celu:</p>
<p class="legal-indent2">· umożliwienia Tobie kontaktu z Administratorem za pośrednictwem Serwisu;</p>
<p class="legal-indent2">· marketingu produktów i usług własnych Administratora, w tym w celach analitycznych i statystycznych;</p>
<p class="legal-indent2">· korzystanie z formularzy kontaktowych udostępnionych przez Administratora w Serwisie;</p>
<p class="legal-indent2">· obrony przed ewentualnymi roszczeniami dochodzonymi przez Administratora lub od Administratora.</p>
<p class="legal">6.4. Administrator może przekazywać dane podmiotom przetwarzającym je na zlecenie Administratora i na podstawie zawartych umów, wyłącznie w celu i zakresie niezbędnym dla realizacji celów wskazanych w pkt 2 powyżej (m.in. na rzecz podmiotów świadczących usługi informatyczne, w tym zapewniające prawidłowe funkcjonowanie Serwisu), z zastrzeżeniem, że dane osobowe przetwarzane będą zgodnie z zaleceniami Administratora. Administrator przekaże dane osobowe jeżeli taki obowiązek będzie wynikać z bezwzględnie obowiązujących przepisów prawa, w szczególności uprawnionym organom państwowym.</p>
<p class="legal">6.5. Administrator dokłada wszelkich starań aby Twoje dane osobowe przetwarzane były w sposób adekwatny i tak długo jak jest to niezbędne do celów, w jakich zostały one zebrane lub dopóki jest to wymagane przepisami prawa powszechnie obowiązującego, w szczególności do momentu przedawnienia ewentualnych roszczeń lub wygaśnięcia obowiązku archiwizacji.</p>
<p class="legal">6.6. W zakresie dozwolonym przez RODO przysługują Tobie następujące prawa w odniesieniu do Twoich danych osobowych: prawo dostępu do danych, prawo do sprostowania i uzupełnienia danych, prawo do usunięcia danych, prawo do przenoszenia danych osobowych, prawo do ograniczenia przetwarzania danych, prawo do wniesienia sprzeciwu wobec przetwarzania danych osobowych, prawo do cofnięcia zgody, prawo do skargi do organu nadzorczego (Prezes UODO, ul. Stawki 2, 00-193 Warszawa).</p>
<p class="legal">6.7. W przypadku gdy będziesz chciał skorzystać z przysługujących Tobie praw prosimy o przesłanie do Administratora wiadomości na adres mailowy: ido@egurrola.pl lub pocztą tradycyjną na adres: ul. Żwirki i Wigury 99A, 02-089 Warszawa.</p>
<p class="legal">6.8. Bezpieczeństwo danych jest dla Administratora priorytetem i Administrator analizuje ryzyka w celu zapewnienia, że Twoje dane osobowe przetwarzane są w sposób bezpieczny, zapewniający przede wszystkim, że dostęp do danych mają jedynie osoby upoważnione i jedynie w zakresie, w jakim jest to niezbędne ze względu na wykonywane zadania.</p>
<p class="legal">6.9. Administrator podejmuje wszelkie niezbędne i zgodne z prawem działania, aby podmioty współpracujące z Administratorem (podwykonawcy i inne podmioty współpracujące) dawały gwarancję prawidłowego i odpowiedniego stosowania środków bezpieczeństwa w każdym przypadku przetwarzania Twoich danych osobowych wykonywanego na zlecenie Administratora.</p>
<p class="legal">6.10. Podanie przez Ciebie danych osobowych jest dobrowolne. Odmowa podania danych osobowych może jednak - w zależności od kontekstu, w którym dane te są przetwarzane - uniemożliwić zawarcie umowy pomiędzy Tobą a Administratorem, jak również może wpłynąć na zakres usług, które Administrator będzie mógł świadczyć na Twoją rzecz.</p>

{{-- ── SIGNATURES ───────────────────────────────────────────── --}}
<table class="sig-table">
    <tr>
        <td>
            <div class="sig-line"></div>
            Data i podpis w imieniu EDS
        </td>
        <td>
            <div class="sig-line"></div>
            Data i podpis Klienta
        </td>
    </tr>
</table>

<p class="consent-row">Niniejszym oświadczam, iż zapoznałem/am się z Regulaminem i akceptuję jego treść.</p>
<table class="sig-table">
    <tr>
        <td></td>
        <td>
            <div class="sig-line"></div>
            Podpis Klienta
        </td>
    </tr>
</table>

<p class="consent-row">Niniejszym oświadczam, iż zapoznałem/am się ze Statutem EDS i akceptuję jego treść.</p>
<table class="sig-table">
    <tr>
        <td></td>
        <td>
            <div class="sig-line"></div>
            Podpis Klienta
        </td>
    </tr>
</table>

<p class="consent-row">Oświadczam, iż wyrażam nieodpłatną, nieograniczoną w czasie i co do terytorium zgodę EDS na utrwalanie wizerunku Uczestnika na zdjęciach i materiałach filmowych i jego wykorzystywanie, zgodnie z warunkami, o których mowa w Regulaminie stanowiącym Załącznik nr 1 do niniejszej Umowy.</p>
<table class="sig-table">
    <tr>
        <td></td>
        <td>
            <div class="sig-line"></div>
            Podpis Klienta
        </td>
    </tr>
</table>

<p class="consent-row">Wyrażam zgodę na otrzymywanie informacji marketingowych i handlowych drogą elektroniczną na podany przeze mnie adres e-mail z użyciem telekomunikacyjnych urządzeń końcowych i automatycznych systemów wywołujących.</p>
<table class="sig-table">
    <tr>
        <td></td>
        <td>
            <div class="sig-line"></div>
            Podpis Klienta
        </td>
    </tr>
</table>

<p class="consent-row">Wyrażam zgodę na otrzymywanie informacji marketingowych i handlowych drogą elektroniczną na podany przeze mnie numer telefonu z użyciem telekomunikacyjnych urządzeń końcowych i automatycznych systemów wywołujących.</p>
<table class="sig-table">
    <tr>
        <td></td>
        <td>
            <div class="sig-line"></div>
            Podpis Klienta
        </td>
    </tr>
</table>

</div>
</body>
</html>
