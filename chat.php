<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Help & Support | SmartHospital</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;

            background: #f7fbfc;

            color: #17324d;

            min-height: 100vh;
        }

        a {
            text-decoration: none;
        }


        /* =========================
           HEADER
        ========================= */

        header {
            background: white;

            border-bottom: 1px solid #edf2f4;
        }

        .navbar {
            max-width: 1200px;

            height: 76px;

            margin: auto;

            padding: 0 30px;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }

        .logo {
            display: flex;

            align-items: center;

            gap: 10px;

            color: #087f8c;

            font-size: 21px;

            font-weight: 700;
        }

        .logo-icon {
            width: 38px;
            height: 38px;

            border-radius: 10px;

            background: #087f8c;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;
        }

        .back-link {
            color: #647789;

            font-size: 14px;

            font-weight: 500;
        }

        .back-link:hover {
            color: #087f8c;
        }


        /* =========================
           MAIN
        ========================= */

        main {
            min-height: calc(100vh - 126px);

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 45px 20px;
        }


        /* =========================
           CHAT CONTAINER
        ========================= */

        .chat-container {
            width: 100%;

            max-width: 900px;

            height: 600px;

            background: white;

            border: 1px solid #edf1f3;

            border-radius: 18px;

            overflow: hidden;

            display: grid;

            grid-template-columns: 270px 1fr;

            box-shadow:
                0 18px 45px rgba(23, 50, 77, 0.08);
        }


        /* =========================
           SIDEBAR
        ========================= */

        .chat-sidebar {
            background: #f8fbfc;

            border-right: 1px solid #edf1f3;

            padding: 30px 22px;
        }

        .support-title {
            margin-bottom: 25px;
        }

        .support-title h2 {
            font-size: 20px;

            margin-bottom: 7px;
        }

        .support-title p {
            color: #7a8b98;

            font-size: 12px;

            line-height: 1.6;
        }


        /* =========================
           STAFF STATUS
        ========================= */

        .staff-card {
            padding: 17px;

            background: white;

            border: 1px solid #e9eff1;

            border-radius: 12px;

            margin-bottom: 22px;
        }

        .staff-header {
            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 12px;
        }

        .staff-avatar {
            width: 38px;
            height: 38px;

            border-radius: 50%;

            background: #e6f7f5;

            color: #087f8c;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 700;
        }

        .staff-name strong {
            display: block;

            font-size: 13px;
        }

        .staff-name span {
            display: flex;

            align-items: center;

            gap: 5px;

            color: #24915d;

            font-size: 11px;

            margin-top: 3px;
        }

        .online-dot {
            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: #24915d;
        }


        /* =========================
           HELP TOPICS
        ========================= */

        .topic-title {
            color: #81909c;

            font-size: 11px;

            font-weight: 600;

            text-transform: uppercase;

            margin-bottom: 10px;
        }

        .topic {
            display: block;

            padding: 10px 12px;

            margin-bottom: 5px;

            border-radius: 7px;

            color: #647789;

            font-size: 12px;
        }

        .topic:hover {
            background: #e8f7f8;

            color: #087f8c;
        }


        /* =========================
           CHAT AREA
        ========================= */

        .chat-area {
            display: flex;

            flex-direction: column;

            min-width: 0;
        }


        /* =========================
           CHAT HEADER
        ========================= */

        .chat-header {
            height: 72px;

            padding: 0 25px;

            border-bottom: 1px solid #edf1f3;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }

        .chat-header h3 {
            font-size: 15px;

            margin-bottom: 3px;
        }

        .chat-header p {
            color: #84929c;

            font-size: 11px;
        }

        .secure {
            padding: 6px 9px;

            background: #e8f7f8;

            color: #087f8c;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 600;
        }


        /* =========================
           MESSAGES
        ========================= */

        .messages {
            flex: 1;

            padding: 25px;

            overflow-y: auto;

            background: #ffffff;
        }

        .message {
            display: flex;

            margin-bottom: 18px;
        }

        .message.staff {
            justify-content: flex-start;
        }

        .message.patient {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: 70%;

            padding: 12px 15px;

            border-radius: 12px;

            font-size: 12px;

            line-height: 1.6;
        }

        .staff .message-bubble {
            background: #f1f6f7;

            color: #425b6d;

            border-bottom-left-radius: 3px;
        }

        .patient .message-bubble {
            background: #087f8c;

            color: white;

            border-bottom-right-radius: 3px;
        }

        .message-time {
            display: block;

            margin-top: 5px;

            font-size: 9px;

            opacity: 0.65;
        }


        /* =========================
           QUICK QUESTIONS
        ========================= */

        .quick-actions {
            display: flex;

            gap: 7px;

            flex-wrap: wrap;

            padding: 0 25px 15px;
        }

        .quick-btn {
            padding: 7px 11px;

            border: 1px solid #dce6ea;

            border-radius: 20px;

            background: white;

            color: #647789;

            font-size: 10px;

            cursor: pointer;
        }

        .quick-btn:hover {
            border-color: #087f8c;

            color: #087f8c;
        }


        /* =========================
           MESSAGE FORM
        ========================= */

        .chat-input {
            padding: 15px 20px;

            border-top: 1px solid #edf1f3;

            display: flex;

            gap: 10px;
        }

        .chat-input input {
            flex: 1;

            height: 43px;

            padding: 0 13px;

            border: 1px solid #dce6ea;

            border-radius: 7px;

            outline: none;

            font-family: inherit;

            font-size: 12px;
        }

        .chat-input input:focus {
            border-color: #087f8c;
        }

        .send-btn {
            width: 80px;

            border: none;

            border-radius: 7px;

            background: #087f8c;

            color: white;

            font-family: inherit;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;
        }

        .send-btn:hover {
            background: #066b76;
        }


        /* =========================
           FOOTER
        ========================= */

        footer {
            background: #102d44;

            color: #dce7ed;

            text-align: center;

            padding: 20px;

            font-size: 12px;
        }


        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 750px) {

            .chat-container {
                height: 650px;

                grid-template-columns: 1fr;
            }

            .chat-sidebar {
                display: none;
            }

            .message-bubble {
                max-width: 82%;
            }
        }

        @media (max-width: 600px) {

            .navbar {
                height: 68px;

                padding: 0 18px;
            }

            .logo {
                font-size: 18px;
            }

            .logo-icon {
                width: 34px;
                height: 34px;
            }

            .back-link {
                font-size: 12px;
            }

            main {
                padding: 20px 10px;
            }

            .chat-container {
                height: calc(100vh - 110px);

                border-radius: 12px;
            }

            .chat-header {
                padding: 0 15px;
            }

            .messages {
                padding: 18px 15px;
            }

            .quick-actions {
                padding: 0 15px 12px;
            }

            .chat-input {
                padding: 12px;
            }

            .send-btn {
                width: 65px;
            }
        }

    </style>

</head>


<body>


<header>

    <nav class="navbar">

        <a href="index.php" class="logo">

            <span class="logo-icon">
                +
            </span>

            SmartHospital

        </a>


        <a href="index.php" class="back-link">
            ← Back to Home
        </a>

    </nav>

</header>


<main>

    <div class="chat-container">


        <!-- =========================
             SIDEBAR
        ========================= -->

        <aside class="chat-sidebar">


            <div class="support-title">

                <h2>
                    Help Center
                </h2>

                <p>
                    Need assistance? Our support
                    team is here to help.
                </p>

            </div>


            <div class="staff-card">

                <div class="staff-header">

                    <div class="staff-avatar">
                        S
                    </div>

                    <div class="staff-name">

                        <strong>
                            Support Desk
                        </strong>

                        <span>
                            <i class="online-dot"></i>
                            Online
                        </span>

                    </div>

                </div>

                <p style="
                    color:#84929c;
                    font-size:11px;
                    line-height:1.5;
                ">
                    Usually responds within
                    a few minutes.
                </p>

            </div>


            <div class="topic-title">
                Common Questions
            </div>


            <a href="#" class="topic">
                How do I join a queue?
            </a>

            <a href="#" class="topic">
                Where is my queue number?
            </a>

            <a href="#" class="topic">
                How can I check my position?
            </a>

            <a href="#" class="topic">
                I forgot my password
            </a>

        </aside>


        <!-- =========================
             CHAT AREA
        ========================= -->

        <section class="chat-area">


            <div class="chat-header">

                <div>

                    <h3>
                        SmartHospital Support
                    </h3>

                    <p>
                        We are here to assist you.
                    </p>

                </div>


                <span class="secure">
                    ● Support Online
                </span>

            </div>


            <!-- MESSAGES -->

            <div class="messages" id="messages">


                <div class="message staff">

                    <div class="message-bubble">

                        Hello! Welcome to
                        SmartHospital Support.
                        How can we help you today?

                        <span class="message-time">
                            Support Desk • Now
                        </span>

                    </div>

                </div>


                <div class="message staff">

                    <div class="message-bubble">

                        You can ask us about
                        registration, joining a queue,
                        your queue position, or
                        hospital services.

                        <span class="message-time">
                            Support Desk • Now
                        </span>

                    </div>

                </div>


            </div>


            <!-- QUICK QUESTIONS -->

            <div class="quick-actions">

                <button
                    class="quick-btn"
                    type="button"
                    onclick="sendQuickMessage('How do I join a queue?')"
                >
                    Join a queue
                </button>


                <button
                    class="quick-btn"
                    type="button"
                    onclick="sendQuickMessage('How can I check my queue position?')"
                >
                    Check my position
                </button>


                <button
                    class="quick-btn"
                    type="button"
                    onclick="sendQuickMessage('I need help with registration.')"
                >
                    Registration help
                </button>

            </div>


            <!-- INPUT -->

            <form
                class="chat-input"
                onsubmit="sendMessage(event)"
            >

                <input
                    type="text"
                    id="messageInput"
                    placeholder="Type your message..."
                    autocomplete="off"
                >


                <button
                    type="submit"
                    class="send-btn"
                >
                    Send
                </button>

            </form>


        </section>

    </div>

</main>


<footer>

    © 2026 SmartHospital Queue Management System

</footer>


<script>

    function addMessage(text, type) {

        const messages =
            document.getElementById("messages");

        const message =
            document.createElement("div");

        message.className =
            "message " + type;

        const bubble =
            document.createElement("div");

        bubble.className =
            "message-bubble";

        bubble.innerHTML =
            text +
            '<span class="message-time">You • Now</span>';

        message.appendChild(bubble);

        messages.appendChild(message);

        messages.scrollTop =
            messages.scrollHeight;
    }


    function sendMessage(event) {

        event.preventDefault();

        const input =
            document.getElementById("messageInput");

        const text =
            input.value.trim();

        if (text === "") {
            return;
        }

        addMessage(text, "patient");

        input.value = "";

        setTimeout(function () {

            addMessage(
                "Thank you for contacting SmartHospital Support. A staff member will assist you shortly.",
                "staff"
            );

        }, 700);
    }


    function sendQuickMessage(text) {

        addMessage(text, "patient");

        setTimeout(function () {

            addMessage(
                "Thanks for your question. Please wait while our support desk assists you.",
                "staff"
            );

        }, 700);
    }

</script>


</body>

</html>