<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description"
        content="viho admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities." />
    <meta name="keywords"
        content="admin template, viho admin template, dashboard template, flat admin template, responsive admin template, web app" />
    <meta name="author" content="pixelstrap" />
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon" />
    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon" />
    <title>viho - Premium Admin Template</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/fontawesome.css') }}" />
    <link href="https://fonts.googleapis.com/css?family=Work+Sans:100,200,300,400,500,600,700,800,900"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css?family=Poppins:100,100i,200,200i,300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet" />
    <style type="text/css">
        body {
            text-align: center;
            margin: 0 auto;
            width: 650px;
            font-family: work-Sans, sans-serif;
            background-color: #f6f7fb;
            display: block;
        }

        ul {
            margin: 0;
            padding: 0;
        }

        li {
            display: inline-block;
            text-decoration: unset;
        }

        a {
            text-decoration: none;
        }

        p {
            margin: 15px 0;
        }

        h5 {
            color: #444;
            text-align: left;
            font-weight: 400;
        }

        .text-center {
            text-align: center;
        }

        .main-bg-light {
            background-color: #fafafa;
            //- box-shadow: 0px 0px 14px -4px rgba(0, 0, 0, 0.2705882353);
        }

        .title {
            color: #444444;
            font-size: 22px;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 10px;
            padding-bottom: 0;
            text-transform: uppercase;
            display: inline-block;
            line-height: 1;
        }

        table {
            margin-top: 30px;
        }

        table.top-0 {
            margin-top: 0;
        }

        table.order-detail {
            border: 1px solid #ddd;
            border-collapse: collapse;
        }

        table.order-detail tr:nth-child(even) {
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }

        table.order-detail tr:nth-child(odd) {
            border-bottom: 1px solid #ddd;
        }

        .pad-left-right-space {
            border: unset !important;
        }

        .pad-left-right-space td {
            padding: 5px 15px;
        }

        .pad-left-right-space td p {
            margin: 0;
        }

        .pad-left-right-space td b {
            font-size: 15px;
            font-family: "Roboto", sans-serif;
        }

        .order-detail th {
            font-size: 16px;
            padding: 15px;
            text-align: center;
            background: #fafafa;
        }

        .footer-social-icon tr td img {
            margin-left: 5px;
            margin-right: 5px;
        }

        .temp-social td {
            display: inline-block;
        }

        .temp-social td i {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #72CB00;
            //- padding:10px;
            background-color: #72CB003d;
            transition: all 0.5s ease;
        }

        .temp-social td:nth-child(n + 2) {
            margin-left: 15px;
        }
    </style>
</head>

<body style="margin: 20px auto;">
    <table align="center" border="0" cellpadding="0" cellspacing="0"
        style="padding: 30px; background-color: #fff; -webkit-box-shadow: 0px 0px 14px -4px rgba(0, 0, 0, 0.2705882353); box-shadow: 0px 0px 14px -4px rgba(0, 0, 0, 0.2705882353); width: 100%;">
        <tbody>
            <tr>
                <td>
                    <table align="left" border="0" cellpadding="0" cellspacing="0" style="text-align: left;"
                        width="100%">
                        <tbody>
                            <tr>
                                <td style="text-align: center;">
                                    <img src="{{ $bannerUrl ?? asset('assets/images/email-template/banner-default.jpg') }}"
                                        alt="Banner campaña" style="max-width:100%; height:auto; border-radius:6px;">
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <p style="font-size: 18px;"><b>Hola, {{ $userName }}</b></p>
                                    <p style="font-size: 14px; color: #aba8a8;">
                                        La selección de juguetes que realizaste es la siguiente.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <table cellpadding="0" cellspacing="0" border="0" align="left"
                        style="width: 100%; margin-top: 10px; margin-bottom: 10px;">
                        <tbody>
                            <tr>
                                <td
                                    style="background-color: #fafafa; padding: 15px; letter-spacing: 0.3px; width: 50%;">
                                    <h5
                                        style="font-size: 16px; font-weight: 600; color: #000; line-height: 16px; padding-bottom: 13px; border-bottom: 1px solid #e6e8eb; letter-spacing: -0.65px; margin-top: 0; margin-bottom: 13px;">
                                        Your Shipping Address
                                    </h5>
                                    <p
                                        style="text-align: left; font-weight: normal; font-size: 14px; color: #aba8a8; line-height: 21px; margin-top: 0;">
                                        268 Cambridge Lane New Albany,<br />
                                        IN 47150268 Cambridge Lane <br />
                                        New Albany, IN 47150
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <table class="order-detail" border="0" cellpadding="0" cellspacing="0" align="left"
                        style="width: 100%; margin-bottom: 50px;">
                        <tbody>
                            <tr align="left">
                                <th>PRODUCT</th>
                                <th style="padding-left: 15px;">DESCRIPTION</th>
                                <th>QUANTITY</th>
                                <th>PRICE</th>
                            </tr>
                            <tr>
                                <td><img src="{{ asset('assets/images/email-template/4.png') }}" alt=""
                                        width="80" /></td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="margin-top: 15px;">Three seater Wood Style sofa for Leavingroom</h5>
                                </td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="font-size: 14px; color: #444; margin-top: 15px; margin-bottom: 0px;">
                                        Size : <span> L</span></h5>
                                    <h5 style="font-size: 14px; color: #444; margin-top: 10px;">QTY : <span>1</span>
                                    </h5>
                                </td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="font-size: 14px; color: #444; margin-top: 15px;"><b>$500</b></h5>
                                </td>
                            </tr>
                            <tr>
                                <td><img src="{{ asset('assets/images/email-template/1.png') }}" alt=""
                                        width="80" /></td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="margin-top: 15px;">Three seater Wood Style sofa for Leavingroom</h5>
                                </td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="font-size: 14px; color: #444; margin-top: 15px; margin-bottom: 0px;">
                                        Size : <span> L</span></h5>
                                    <h5 style="font-size: 14px; color: #444; margin-top: 10px;">QTY : <span>1</span>
                                    </h5>
                                </td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="font-size: 14px; color: #444; margin-top: 15px;"><b>$500</b></h5>
                                </td>
                            </tr>
                            <tr>
                                <td><img src="{{ asset('assets/images/email-template/4.png') }}" alt=""
                                        width="80" /></td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="margin-top: 15px;">Three seater Wood Style sofa for Leavingroom</h5>
                                </td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="font-size: 14px; color: #444; margin-top: 15px; margin-bottom: 0px;">
                                        Size : <span> L</span></h5>
                                    <h5 style="font-size: 14px; color: #444; margin-top: 10px;">QTY : <span>1</span>
                                    </h5>
                                </td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="font-size: 14px; color: #444; margin-top: 15px;"><b>$500</b></h5>
                                </td>
                            </tr>
                            <tr>
                                <td><img src="{{ asset('assets/images/email-template/1.png') }}" alt=""
                                        width="80" /></td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="margin-top: 15px;">Three seater Wood Style sofa for Leavingroom</h5>
                                </td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="font-size: 14px; color: #444; margin-top: 15px; margin-bottom: 0px;">
                                        Size : <span> L</span></h5>
                                    <h5 style="font-size: 14px; color: #444; margin-top: 10px;">QTY : <span>1</span>
                                    </h5>
                                </td>
                                <td valign="top" style="padding-left: 15px;">
                                    <h5 style="font-size: 14px; color: #444; margin-top: 15px;"><b>$500</b></h5>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <table class="main-bg-light text-center top-0" align="center" border="0" cellpadding="0"
                        cellspacing="0" width="100%">

                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
