"""Generate the LYDO CAMS system flowchart as a draw.io (.drawio) file."""

from __future__ import annotations

from xml.sax.saxutils import escape as xml_escape

OUT = r"C:\Users\John Lloyd\Desktop\NEA\docs\LYDO-CAMS-System-Flowchart.drawio"

EDGE = (
    "edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;html=1;"
    "strokeWidth=1.5;strokeColor=#334155;fontSize=11;fontColor=#0F172A;"
    "fontStyle=1;endArrow=blockThin;endFill=1;labelBackgroundColor=#FFFFFF;"
)

STYLES = {
    "start": "ellipse;whiteSpace=wrap;html=1;fillColor=#1B365D;fontColor=#FFFFFF;strokeColor=#0F2444;fontStyle=1;fontSize=13;shadow=1;",
    "end_ok": "ellipse;whiteSpace=wrap;html=1;fillColor=#166534;fontColor=#FFFFFF;strokeColor=#14532D;fontStyle=1;fontSize=13;shadow=1;",
    "end_no": "ellipse;whiteSpace=wrap;html=1;fillColor=#9B1C1C;fontColor=#FFFFFF;strokeColor=#7F1D1D;fontStyle=1;fontSize=13;shadow=1;",
    "process": "rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#E8F1F8;strokeColor=#1B4F72;strokeWidth=1.5;fontSize=12;fontColor=#0F172A;shadow=0;",
    "staff": "rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#FEF3C7;strokeColor=#B45309;strokeWidth=1.5;fontSize=12;fontColor=#0F172A;",
    "system": "rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#D1FAE5;strokeColor=#047857;strokeWidth=1.5;fontSize=12;fontColor=#0F172A;",
    "admin": "rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#EDE9FE;strokeColor=#6D28D9;strokeWidth=1.5;fontSize=12;fontColor=#0F172A;",
    "decision": "rhombus;whiteSpace=wrap;html=1;fillColor=#FFF7ED;strokeColor=#C2410C;strokeWidth=1.5;fontSize=11;fontStyle=1;fontColor=#0F172A;",
    "io": "shape=parallelogram;perimeter=parallelogramPerimeter;whiteSpace=wrap;html=1;fillColor=#F3E8FF;strokeColor=#7C3AED;strokeWidth=1.5;fontSize=11;fontColor=#0F172A;",
    "mail": "shape=document;whiteSpace=wrap;html=1;boundedLbl=1;fillColor=#FCE7F3;strokeColor=#BE185D;strokeWidth=1.5;fontSize=11;fontColor=#0F172A;",
    "db": "shape=cylinder3;whiteSpace=wrap;html=1;boundedLbl=1;backgroundOutline=1;size=12;fillColor=#CCFBF1;strokeColor=#0F766E;strokeWidth=1.5;fontSize=11;fontColor=#0F172A;",
    "status": "rounded=1;whiteSpace=wrap;html=1;arcSize=40;fillColor=#1B365D;fontColor=#FFFFFF;strokeColor=#0F2444;fontStyle=1;fontSize=11;",
    "status_warn": "rounded=1;whiteSpace=wrap;html=1;arcSize=40;fillColor=#B45309;fontColor=#FFFFFF;strokeColor=#92400E;fontStyle=1;fontSize=11;",
    "status_ok": "rounded=1;whiteSpace=wrap;html=1;arcSize=40;fillColor=#166534;fontColor=#FFFFFF;strokeColor=#14532D;fontStyle=1;fontSize=11;",
    "status_bad": "rounded=1;whiteSpace=wrap;html=1;arcSize=40;fillColor=#9B1C1C;fontColor=#FFFFFF;strokeColor=#7F1D1D;fontStyle=1;fontSize=11;",
    "title": "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;fontStyle=1;fontSize=22;fontColor=#1B365D;",
    "subtitle": "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;fontSize=12;fontColor=#475569;",
    "banner": "rounded=0;whiteSpace=wrap;html=1;fillColor=#1B365D;strokeColor=none;fontColor=#FFFFFF;fontStyle=1;fontSize=20;align=left;spacingLeft=20;",
    "phase": "rounded=1;whiteSpace=wrap;html=1;arcSize=6;dashed=1;dashPattern=8 8;fillColor=#F8FAFC;strokeColor=#94A3B8;verticalAlign=top;fontStyle=1;fontSize=12;fontColor=#1E3A5F;align=left;spacingLeft=12;spacingTop=8;",
    "legend": "rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#FFFFFF;strokeColor=#CBD5E1;align=left;spacingLeft=10;fontSize=11;fontColor=#0F172A;",
    "note": "shape=note;whiteSpace=wrap;html=1;size=16;fillColor=#FFFBEB;strokeColor=#D97706;align=left;spacingLeft=8;fontSize=10;fontColor=#0F172A;",
    "card": "rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#FFFFFF;strokeColor=#1B365D;strokeWidth=1.5;align=left;verticalAlign=top;spacingLeft=12;spacingTop=10;fontSize=11;fontColor=#0F172A;",
    "stage": "rounded=1;whiteSpace=wrap;html=1;arcSize=6;fillColor=#1B365D;fontColor=#FFFFFF;strokeColor=none;fontStyle=1;fontSize=12;",
    "actor": "rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#FFFFFF;strokeColor=#1B365D;strokeWidth=2;fontSize=12;fontStyle=1;fontColor=#1B365D;",
}


def val(text: str) -> str:
    text = xml_escape(str(text), {'"': "&quot;"})
    return text.replace("\n", "&lt;br&gt;")


class Page:
    def __init__(self, name: str, width: int, height: int):
        self.name = name
        self.width = width
        self.height = height
        self._n = 2
        self.nodes: list[str] = []
        self.edges: list[str] = []

    def _id(self) -> str:
        i = str(self._n)
        self._n += 1
        return i

    def v(self, text: str, x: float, y: float, w: float, h: float, kind: str) -> str:
        i = self._id()
        style = STYLES[kind]
        self.nodes.append(
            f'<mxCell id="{i}" value="{val(text)}" style="{style}" vertex="1" parent="1">'
            f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/></mxCell>'
        )
        return i

    def e(
        self,
        source: str,
        target: str,
        label: str = "",
        exit: str = "s",
        entry: str = "n",
        color: str = "#334155",
        dashed: bool = False,
    ) -> str:
        i = self._id()
        ports = {"s": (0.5, 1), "n": (0.5, 0), "e": (1, 0.5), "w": (0, 0.5)}
        ex, ey = ports[exit]
        enx, eny = ports[entry]
        dash = "dashed=1;dashPattern=6 6;" if dashed else ""
        style = (
            f"{EDGE}{dash}strokeColor={color};exitX={ex};exitY={ey};exitDx=0;exitDy=0;"
            f"entryX={enx};entryY={eny};entryDx=0;entryDy=0;"
        )
        geom = '<mxGeometry relative="1" as="geometry">'
        if label:
            geom += '<mxPoint as="offset" x="0" y="-8"/>'
        geom += "</mxGeometry>"
        self.edges.append(
            f'<mxCell id="{i}" value="{val(label)}" style="{style}" edge="1" parent="1" '
            f'source="{source}" target="{target}">{geom}</mxCell>'
        )
        return i

    def chain(self, ids: list[str], **kwargs) -> None:
        for a, b in zip(ids, ids[1:]):
            self.e(a, b, **kwargs)

    def xml(self, diagram_id: str) -> str:
        body = "\n        ".join(self.nodes + self.edges)
        return f"""  <diagram id="{diagram_id}" name="{val(self.name)}">
    <mxGraphModel dx="1400" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="{self.width}" pageHeight="{self.height}" math="0" shadow="0">
      <root>
        <mxCell id="0"/>
        <mxCell id="1" parent="0"/>
        {body}
      </root>
    </mxGraphModel>
  </diagram>"""


YES = "#166534"
NO = "#9B1C1C"
LOOP = "#B45309"
INFO = "#1D4ED8"


def page_overview() -> Page:
    p = Page("1. System Overview", 2360, 1320)
    p.v("  LYDO Nabua  ·  Community Assistance Management System (CAMS)", 0, 0, 2360, 64, "banner")
    p.v(
        "Municipality of Nabua, Camarines Sur  ·  Local Youth Development Office\n"
        "Application IDs: CAMS-YYYY-######    Beneficiary IDs: BEN-YYYY-######    Release IDs: REL-YYYY-######",
        40,
        76,
        1400,
        48,
        "subtitle",
    )
    p.v(
        "Citizen / Applicant\nPublic site + portal",
        40,
        130,
        220,
        64,
        "actor",
    )
    p.v(
        "LYDO Staff\nVerify · Evaluate · Approve · Release\n(workflow_staff assignment)",
        280,
        130,
        280,
        64,
        "staff",
    )
    p.v(
        "Administrator\nFull access, bypasses\nworkflow assignment",
        580,
        130,
        240,
        64,
        "admin",
    )
    p.v(
        "Automated system\nOCR.space · Email · OTP\nTurnstile · Audit log",
        840,
        130,
        240,
        64,
        "system",
    )
    p.v(
        "Legend\nBlue = citizen    Gold = staff    Green = system    Purple = data/input    Pink = email    Navy pill = status",
        1100,
        130,
        1220,
        64,
        "legend",
    )

    stages = [
        (40, "1. Discover"),
        (290, "2. Apply"),
        (540, "3. Intake"),
        (790, "4. Documents"),
        (1040, "5. Verify"),
        (1290, "6. Evaluate"),
        (1540, "7. Approve"),
        (1790, "8. Release"),
        (2040, "9. Complete"),
    ]
    for x, label in stages:
        p.v(label, x, 220, 230, 36, "stage")

    # Citizen row
    c1 = p.v("Browse programs,\nannouncements,\nhow-to-apply, contact", 40, 280, 230, 80, "process")
    c2 = p.v("Select open program\nConfirm eligibility\nFill form + email OTP", 290, 280, 230, 80, "process")
    c3 = p.v("Wait for office to\naccept the draft\nThen activate portal login", 540, 280, 230, 80, "process")
    c4 = p.v("Upload requirements\nPDF / JPG / PNG ≤ 5 MB\nReview and submit", 790, 280, 230, 80, "process")
    c5 = p.v("Replace documents\nif incomplete or\nrevision requested", 1040, 280, 230, 80, "process")
    c6 = p.v("Track status in\nportal or public\nlookup by CAMS number", 1290, 280, 230, 80, "process")
    c7 = p.v("Receive approval\nor rejection\n(email + in-app)", 1540, 280, 230, 80, "process")
    c8 = p.v("Attend scheduled\nrelease (cash, bank,\ne-wallet, check, other)", 1790, 280, 230, 80, "process")
    c9 = p.v("Present claim\n(QR / reference /\napplication number)", 2040, 280, 230, 80, "process")
    p.chain([c1, c2, c3, c4, c5, c6, c7, c8, c9], exit="e", entry="w")

    # Status pills
    s_draft = p.v("draft", 540, 380, 110, 28, "status")
    s_acc = p.v("accepted", 660, 380, 110, 28, "status_ok")
    s_sub = p.v("submitted", 790, 380, 110, 28, "status")
    s_ver = p.v("under_verification", 1040, 380, 150, 28, "status")
    s_eval = p.v("under_evaluation", 1290, 380, 150, 28, "status")
    s_fap = p.v("for_approval", 1540, 380, 130, 28, "status")
    s_rel = p.v("scheduled_for_release", 1790, 380, 170, 28, "status_ok")
    s_done = p.v("completed", 2040, 380, 120, 28, "status_ok")

    p.e(c3, s_draft, exit="s", entry="n")
    p.e(c3, s_acc, exit="s", entry="n")
    p.e(c4, s_sub, exit="s", entry="n")
    p.e(c5, s_ver, exit="s", entry="n")
    p.e(c6, s_eval, exit="s", entry="n")
    p.e(c7, s_fap, exit="s", entry="n")
    p.e(c8, s_rel, exit="s", entry="n")
    p.e(c9, s_done, exit="s", entry="n")

    # Staff row
    p.v("STAFF WORKFLOW  (queues: verification → evaluation → approval)", 40, 440, 2280, 32, "stage")
    st1 = p.v("Publish programs,\nrequirements,\nannouncements,\neligibility rules", 40, 490, 230, 90, "staff")
    st2 = p.v("Turnstile + OTP\ngate the public\napply form", 290, 490, 230, 90, "system")
    st3 = p.v("Review draft\nAccept → accepted\nReject → rejected", 540, 490, 230, 90, "staff")
    st4 = p.v("OCR.space reads\neach upload\nScore vs profile", 790, 490, 230, 90, "system")
    st5 = p.v("Per document:\nverify / reject /\nrequest revision", 1040, 490, 230, 90, "staff")
    st6 = p.v("Eligibility engine\nOCR + manual rules\nRecommend action", 1290, 490, 230, 90, "staff")
    st7 = p.v("Final decision:\napproved /\nrejected / revision", 1540, 490, 230, 90, "staff")
    st8 = p.v("Schedule then\nrecord assistance\nrelease (REL-)", 1790, 490, 230, 90, "staff")
    st9 = p.v("Verify claim\nMark completed", 2040, 490, 230, 90, "staff")
    p.chain([st1, st2, st3, st4, st5, st6, st7, st8, st9], exit="e", entry="w")

    # Branch row
    p.v("EXCEPTION PATHS", 40, 610, 2280, 32, "stage")
    b1 = p.v("Program closed,\nslot full, or wrong\nbeneficiary type", 290, 660, 230, 80, "decision")
    b2 = p.v("rejected\n(draft not accepted)", 540, 660, 230, 80, "status_bad")
    b3 = p.v("incomplete\nor for_revision\nApplicant re-uploads", 1040, 660, 230, 80, "status_warn")
    b4 = p.v("Evaluate → revision\nreturns to applicant", 1290, 660, 230, 80, "status_warn")
    b5 = p.v("Approve → revision\nor rejected (terminal)", 1540, 660, 230, 80, "status_bad")
    p.e(c2, b1, "blocked", exit="s", entry="n", color=NO)
    p.e(st3, b2, "reject", exit="s", entry="n", color=NO)
    p.e(st5, b3, "reject / revision", exit="s", entry="n", color=LOOP)
    p.e(b3, c5, "re-upload", exit="n", entry="s", color=LOOP, dashed=True)
    p.e(st6, b4, "revision", exit="s", entry="n", color=LOOP)
    p.e(st7, b5, "reject / revision", exit="s", entry="n", color=NO)

    # Emails
    p.v("EMAILS SENT BY THE SYSTEM", 40, 770, 2280, 32, "stage")
    m1 = p.v("ApplicationOtpMail\n6-digit code, 10 min", 290, 820, 230, 70, "mail")
    m2 = p.v("ApplicationReceivedMail\nAfter public submit", 540, 820, 230, 70, "mail")
    m3 = p.v("ApplicationAcceptedMail\nLogin + temp password", 790, 820, 230, 70, "mail")
    m4 = p.v("DocumentRevisionMail\nWhen a file is returned", 1040, 820, 230, 70, "mail")
    m5 = p.v("ApplicationApprovedMail\nFinal approval", 1540, 820, 230, 70, "mail")
    m6 = p.v("ReleaseScheduledMail\nSchedule / reschedule", 1790, 820, 230, 70, "mail")
    p.e(c2, m1, exit="s", entry="n", dashed=True, color="#BE185D")
    p.e(st3, m2, exit="s", entry="n", dashed=True, color="#BE185D")
    p.e(st3, m3, exit="s", entry="n", dashed=True, color="#BE185D")
    p.e(st5, m4, exit="s", entry="n", dashed=True, color="#BE185D")
    p.e(st7, m5, exit="s", entry="n", dashed=True, color="#BE185D")
    p.e(st8, m6, exit="s", entry="n", dashed=True, color="#BE185D")

    # Programs + rules
    p.v(
        "ASSISTANCE PROGRAMS (seeded)\n"
        "Student: Educational EDU-001 · Tuition TUI-001 · School Supplies · Transportation\n"
        "General: Medical MED-001 · Burial · Emergency EMG-001 · Food FOD-001 · Livelihood LIV-001 · Other\n"
        "Each program has requirements, eligibility rules (OCR or manual), and form fields.\n"
        "Open if is_open, inside date window, and under slot_limit (draft/cancelled/rejected do not consume a slot).",
        40,
        920,
        740,
        150,
        "card",
    )
    p.v(
        "ROLES & ACCESS\n"
        "administrator — all permissions; skips workflow_staff checks\n"
        "staff — dashboard, applicants, applications (view/manage/verify/evaluate/approve),\n"
        "releases, reports, announcements view; programs view-only unless granted\n"
        "applicant — applicant portal only (auth + applicant middleware)\n"
        "Staff may verify / evaluate / approve only with the matching permission AND\n"
        "a workflow_staff row for that step (verification, evaluation, approval).",
        800,
        920,
        740,
        150,
        "card",
    )
    p.v(
        "CONCURRENCY RULES\n"
        "• One resumable draft per program (draft, accepted, for_revision, incomplete)\n"
        "• Cannot start another program while a non-terminal application exists\n"
        "• Cannot duplicate an active application on the same program\n"
        "• Public apply creates pending accounts (is_active=false) until staff accept\n"
        "• Register-first accounts are active immediately but still apply via public form\n"
        "• Guest status lookup: /application-status?application_no=CAMS-…",
        1560,
        920,
        760,
        150,
        "card",
    )

    p.v(
        "HAPPY PATH STATUSES:  draft → accepted → submitted → under_verification → under_evaluation → for_approval → approved → scheduled_for_release → released → completed\n"
        "TERMINAL: completed · rejected · cancelled    |    REWORK: incomplete · for_revision (applicant returns to documents, then under_verification)",
        40,
        1090,
        2280,
        64,
        "legend",
    )
    p.v(
        "Supporting admin modules: Programs · Categories · Requirements · Users · Roles · Announcements · Applicants (beneficiaries) · Reports (applications / financial / beneficiaries) · Audit log · Settings (agency + workflow staff)",
        40,
        1170,
        2280,
        50,
        "note",
    )
    p.v(
        "Open pages 2–5 for the detailed decision flowcharts (intake, documents/OCR, staff processing, and the full status map).",
        40,
        1240,
        2280,
        40,
        "subtitle",
    )
    return p


def page_intake() -> Page:
    p = Page("2. Public Intake & Auth", 1800, 2920)
    p.v("  2. Public intake, authentication, and draft creation", 0, 0, 1800, 56, "banner")
    p.v(
        "Citizen visits the LYDO Nabua portal. Applying, registering, and checking status are public routes. Cloudflare Turnstile and email OTP protect the apply form.",
        40,
        68,
        1720,
        36,
        "subtitle",
    )

    p.v("A. ARRIVAL", 40, 110, 1720, 1520, "phase")
    start = p.v("START", 670, 160, 140, 56, "start")
    home = p.v("Open public site  /\nHome: programs + latest announcements", 560, 250, 360, 64, "process")
    intent = p.v("What does the\nvisitor want?", 590, 350, 300, 110, "decision")
    p.e(start, home)
    p.e(home, intent)

    # Status lookup (left)
    st_in = p.v("GET /application-status\nEnter application_no", 80, 360, 280, 64, "io")
    st_q = p.v("Number found\nin database?", 90, 460, 260, 100, "decision")
    st_yes = p.v("Show application row\nand public timeline", 40, 600, 170, 70, "process")
    st_no = p.v("Empty result\n(not found)", 230, 600, 170, 70, "process")
    st_end = p.v("END lookup", 110, 710, 160, 50, "end_ok")
    p.e(intent, st_in, "Check status", exit="w", entry="e", color=INFO)
    p.e(st_in, st_q)
    p.e(st_q, st_yes, "Yes", exit="w", entry="n", color=YES)
    p.e(st_q, st_no, "No", exit="e", entry="n", color=NO)
    p.e(st_yes, st_end, exit="s", entry="w", color=YES)
    p.e(st_no, st_end, exit="s", entry="e")

    # Register (right)
    reg = p.v("GET/POST /register\nName, email, password,\nbeneficiary type, address", 1160, 350, 280, 80, "io")
    reg_sys = p.v("Create User (applicant, is_active=true)\nApplicant BEN-…\nApplicantProfile + primary address", 1160, 470, 280, 80, "system")
    reg_login = p.v("Auto-login → /applicant/dashboard\nApply still uses public apply form", 1160, 590, 280, 70, "process")
    p.e(intent, reg, "Create account", exit="e", entry="w", color=INFO)
    p.e(reg, reg_sys)
    p.e(reg_sys, reg_login)

    login = p.v("GET/POST /login", 1160, 700, 280, 50, "io")
    login_ok = p.v("Credentials\nvalid and active?", 1180, 790, 240, 90, "decision")
    login_pending = p.v("pending_account:\nwait for accept email", 1480, 790, 220, 70, "process")
    login_off = p.v("Deactivated account\nAccess denied", 1480, 890, 220, 56, "process")
    login_home = p.v("Redirect homePath():\nadmin → /admin/dashboard\napplicant → /applicant/dashboard", 1160, 920, 280, 80, "system")
    p.e(intent, login, "Sign in", exit="e", entry="w", color=INFO, dashed=True)
    p.e(reg_login, login, dashed=True, color="#94A3B8")
    p.e(login, login_ok)
    p.e(login_ok, login_home, "Active", exit="s", entry="n", color=YES)
    p.e(login_ok, login_pending, "Pending", exit="e", entry="n", color=LOOP)
    p.e(login_ok, login_off, "Deactivated", exit="w", entry="n", color=NO)

    # Apply path (center)
    browse = p.v("GET /programs  or  /programs/{slug}\nAlso /how-to-apply and /requirements", 560, 500, 360, 64, "process")
    pick = p.v("Select assistance program", 560, 590, 360, 50, "process")
    openq = p.v("Program currently open?\nis_open, date window, slot_limit", 570, 670, 340, 100, "decision")
    closed = p.v("Redirect to program page\nwith closed / full error", 80, 920, 280, 64, "process")
    closed_end = p.v("END (cannot apply)", 110, 1020, 220, 50, "end_no")
    p.e(intent, browse, "Apply", color=YES)
    p.e(browse, pick)
    p.e(pick, openq)
    p.e(openq, closed, "No", exit="w", entry="e", color=NO)
    p.e(closed, closed_end)

    ts = p.v("POST …/apply/turnstile\nCloudflare Turnstile (session 30 min)", 560, 810, 360, 64, "system")
    ts_q = p.v("Captcha passed?\n(OTP and submit require it)", 580, 910, 320, 100, "decision")
    ts_fail = p.v("Block request\nAsk visitor to retry Turnstile", 80, 1120, 280, 64, "process")
    p.e(openq, ts, "Yes", color=YES)
    p.e(ts, ts_q)
    p.e(ts_q, ts_fail, "No", exit="w", entry="e", color=NO)

    elig = p.v("Apply step 1 of 3\nConfirm eligibility checkbox", 560, 1050, 360, 64, "io")
    form = p.v(
        "Apply step 2 of 3 — demographics\nBeneficiary type: student / non_student\nPWD fields, parents, barangay, school fields",
        560,
        1150,
        360,
        80,
        "io",
    )
    typeq = p.v("Program accepts this\nbeneficiary type?", 580, 1270, 320, 100, "decision")
    type_no = p.v("Validation error:\nnot covered by this program", 80, 1280, 280, 64, "process")
    p.e(ts_q, elig, "Yes", color=YES)
    p.e(elig, form)
    p.e(form, typeq)
    p.e(typeq, type_no, "No", exit="w", entry="e", color=NO)

    otp = p.v("Apply step 3 — POST …/apply/otp\nThrottle 5/min  ·  6 digits  ·  10 minutes", 560, 1410, 360, 64, "system")
    otp_mail = p.v("ApplicationOtpMail\n“Your application verification code”", 1040, 1400, 280, 70, "mail")
    otp_q = p.v("OTP valid\nand not expired?", 580, 1510, 320, 100, "decision")
    otp_no = p.v("Reject submit\nVisitor may request a new OTP", 1040, 1520, 280, 64, "process")
    p.e(typeq, otp, "Yes", color=YES)
    p.e(otp, otp_mail, exit="e", entry="w", dashed=True, color="#BE185D")
    p.e(otp, otp_q)
    p.e(otp_q, otp_no, "No", exit="e", entry="w", color=NO)

    p.v("B. CREATE DRAFT APPLICATION", 40, 1650, 1720, 900, "phase")
    staff_em = p.v("Email already used\nby a staff account?", 580, 1710, 320, 100, "decision")
    staff_err = p.v("Validation error\n(staff emails cannot apply)", 1040, 1720, 280, 64, "process")
    exist = p.v("Existing applicant\nuser for this email?", 580, 1850, 320, 100, "decision")
    upd = p.v("Update ApplicantProfile\nand primary address", 1040, 1840, 280, 64, "system")
    createu = p.v(
        "Create pending User\nis_active=false, pending_account=true\nrandom password\nApplicant BEN-… + profile + address",
        200,
        1840,
        320,
        96,
        "system",
    )
    p.e(otp_q, staff_em, "Yes", color=YES)
    p.e(staff_em, staff_err, "Yes", exit="e", entry="w", color=NO)
    p.e(staff_em, exist, "No", color=YES)
    p.e(exist, upd, "Yes", exit="e", entry="w", color=YES)
    p.e(exist, createu, "No", exit="w", entry="e", color=INFO)

    rules = p.v(
        "ApplicationService.start() guards\n• Resume draft/accepted/for_revision/incomplete on same program\n• Block if a current (non-terminal) app exists on another program\n• Block duplicate active app on same program",
        520,
        2000,
        440,
        100,
        "note",
    )
    p.e(upd, rules, exit="s", entry="n")
    p.e(createu, rules, exit="e", entry="w")

    draft = p.v(
        "Create Application\nstatus = draft\napplication_no = CAMS-{YEAR}-{seq}\ncurrent_step = 3  ·  history + audit",
        540,
        2140,
        400,
        90,
        "db",
    )
    rec = p.v("ApplicationReceivedMail\n“Application received — CAMS-…”\n(no password yet)", 1040, 2150, 280, 80, "mail")
    success = p.v("GET /apply/success\nShow application number, program, email", 540, 2280, 400, 64, "process")
    wait = p.v(
        "Applicant cannot log in yet\nif the account is still pending.\nStaff must accept the draft.",
        1040,
        2280,
        280,
        80,
        "note",
    )
    p.e(rules, draft)
    p.e(draft, rec, exit="e", entry="w", dashed=True, color="#BE185D")
    p.e(draft, success)
    p.e(success, wait, exit="e", entry="w")

    end_intake = p.v("TO PAGE 3\nStaff draft review", 620, 2400, 240, 56, "status")
    p.e(success, end_intake)

    p.v(
        "Also available without applying: contact form (name, email, subject, message — flash success only, no email sent) and published announcements.",
        80,
        2720,
        1440,
        50,
        "note",
    )
    return p


def page_documents() -> Page:
    p = Page("3. Draft Review & Documents", 1600, 3180)
    p.v("  3. Staff accept the draft, then the applicant completes documents", 0, 0, 1600, 56, "banner")

    p.v("A. INITIAL DRAFT REVIEW  (administrator or staff with applications.approve)", 40, 80, 1520, 720, "phase")
    start = p.v("Draft appears on\napplication show page", 580, 140, 320, 64, "staff")
    decide = p.v("Accept this\napplicant?", 600, 240, 280, 110, "decision")
    rej = p.v("POST approve-applicant reject\ndraft → rejected", 160, 250, 300, 70, "staff")
    rej_st = p.v("rejected  (terminal)", 200, 360, 220, 40, "status_bad")
    rej_end = p.v("END", 240, 430, 140, 50, "end_no")
    acc = p.v(
        "POST admin.applications.approve-applicant\ndraft → accepted\nActivate user, issue temporary password",
        560,
        400,
        360,
        90,
        "staff",
    )
    acc_mail = p.v("ApplicationAcceptedMail\nLogin email + temp password", 1040, 400, 280, 80, "mail")
    acc_st = p.v("accepted", 670, 520, 140, 36, "status_ok")
    notify = p.v("In-app: “Application accepted”\nApplicant may now use the portal", 560, 580, 360, 64, "system")
    p.e(start, decide)
    p.e(decide, rej, "No", exit="w", entry="e", color=NO)
    p.e(rej, rej_st)
    p.e(rej_st, rej_end)
    p.e(decide, acc, "Yes", color=YES)
    p.e(acc, acc_mail, exit="e", entry="w", dashed=True, color="#BE185D")
    p.e(acc, acc_st)
    p.e(acc_st, notify)

    p.v("B. APPLICANT PORTAL  (auth + applicant middleware)", 40, 840, 1520, 1620, "phase")
    login = p.v("Applicant signs in\n/applicant/dashboard", 580, 900, 320, 64, "process")
    editq = p.v(
        "Application editable?\ndraft, accepted, for_revision, incomplete,\nor under_verification with docs needing action",
        560,
        1000,
        360,
        110,
        "decision",
    )
    viewonly = p.v("View application +\nnotifications only", 160, 1020, 260, 64, "process")
    docs = p.v(
        "GET applicant.apply.documents\nUpload each program requirement\nPDF / JPG / PNG, max 5 MB",
        560,
        1160,
        360,
        80,
        "io",
    )
    p.e(notify, login)
    p.e(login, editq)
    p.e(editq, viewonly, "No", exit="w", entry="e", color=NO)
    p.e(editq, docs, "Yes", color=YES)

    ocr = p.v(
        "On each new DocumentSubmission\nstatus = pending\nDocumentOcrService.process()",
        560,
        1280,
        360,
        80,
        "system",
    )
    ocr_api = p.v("OCR.space extract text\nDetect document type\nCompare fields to profile / answers", 1040, 1280, 320, 80, "system")
    typeq = p.v("Detected type matches\nthe requirement?", 580, 1400, 320, 100, "decision")
    warn = p.v("In-app warning:\nfile may be the wrong type", 1040, 1410, 320, 64, "process")
    score = p.v(
        "Per-field match_status:\nmatched / mismatch / review / type_mismatch\noverall_score = average\nmatched if all matched and score ≥ 80",
        540,
        1540,
        400,
        100,
        "note",
    )
    p.e(docs, ocr)
    p.e(ocr, ocr_api, exit="e", entry="w")
    p.e(ocr, typeq)
    p.e(typeq, warn, "No", exit="e", entry="w", color=LOOP)
    p.e(typeq, score, "Yes / still stored", color=YES)
    p.e(warn, score, exit="s", entry="e", dashed=True)

    more = p.v("More required\nfiles to upload?", 580, 1680, 320, 100, "decision")
    p.e(score, more)
    p.e(more, docs, "Yes", exit="w", entry="s", color=INFO, dashed=True)

    review = p.v("GET applicant.apply.review\nSummary of answers + files", 560, 1820, 360, 64, "process")
    ready = p.v(
        "All required files uploaded\nand none are revision_requested\nor rejected?",
        560,
        1920,
        360,
        110,
        "decision",
    )
    block = p.v("Cannot submit yet\nComplete or replace files", 160, 1930, 260, 64, "process")
    submit = p.v(
        "POST applicant.apply.submit\nsubmitted_at set · current_step 5\nsyncAssignedStaff(verification)\nIn-app: “Application submitted”",
        540,
        2080,
        400,
        100,
        "system",
    )
    st_from = p.v("Came from for_revision\nor incomplete?", 580, 2220, 320, 100, "decision")
    st_uv = p.v("under_verification", 200, 2240, 200, 40, "status")
    st_sub = p.v("submitted", 1040, 2240, 160, 40, "status")
    p.e(more, review, "No", color=YES)
    p.e(review, ready)
    p.e(ready, block, "No", exit="w", entry="e", color=NO)
    p.e(ready, submit, "Yes", color=YES)
    p.e(submit, st_from)
    p.e(st_from, st_uv, "Yes", exit="w", entry="e", color=LOOP)
    p.e(st_from, st_sub, "No — first filing", exit="e", entry="w", color=YES)

    to4 = p.v("TO PAGE 4\nVerification queue", 640, 2380, 240, 56, "status")
    p.e(st_uv, to4, exit="s", entry="n")
    p.e(st_sub, to4, exit="s", entry="n")

    p.v(
        "Form routes exist (eligibility + form) but the applicant controller currently sends the user to documents after accept. Public apply already captured demographics and OTP.",
        80,
        2780,
        1440,
        56,
        "note",
    )
    return p


def page_staff() -> Page:
    p = Page("4. Verify, Evaluate, Approve, Release", 1680, 4120)
    p.v("  4. Staff processing pipeline — verification, evaluation, approval, release", 0, 0, 1680, 56, "banner")
    p.v(
        "Non-admin staff only see a queue if they have the step permission and a workflow_staff assignment. Administrators bypass assignment checks. Load-balanced assigned_staff_id.",
        40,
        66,
        1600,
        36,
        "subtitle",
    )

    p.v("A. DOCUMENT VERIFICATION  ·  permission applications.verify  ·  queue: submitted, under_verification, incomplete, for_revision", 40, 110, 1600, 980, "phase")
    q = p.v("Staff opens verification queue\n/admin/verification", 620, 170, 340, 64, "staff")
    first = p.v("First action on a submitted\nfile may move status to\nunder_verification", 620, 260, 340, 70, "system")
    per = p.v("For each document,\nchoose an action", 630, 360, 320, 100, "decision")
    v_ok = p.v("verify → document verified\nApplication status unchanged", 120, 370, 300, 70, "staff")
    v_rev = p.v("revision → revision_requested\nApplication → for_revision\nDocumentRevisionMail + notify", 120, 470, 300, 90, "staff")
    v_rej = p.v("reject → document rejected\nApplication → incomplete\nIn-app notify applicant", 120, 590, 300, 80, "staff")
    p.e(q, first)
    p.e(first, per)
    p.e(per, v_ok, "Verify", exit="w", entry="e", color=YES)
    p.e(per, v_rev, "Revision", exit="w", entry="e", color=LOOP)
    p.e(per, v_rej, "Reject", exit="w", entry="e", color=NO)

    loop = p.v("Applicant replaces the file\nNew OCR run\nStatus → under_verification", 120, 710, 300, 80, "process")
    p.e(v_rev, loop, color=LOOP)
    p.e(v_rej, loop, color=LOOP, dashed=True)
    p.e(loop, q, "Back to queue", exit="e", entry="w", color=LOOP, dashed=True)

    ocr_fix = p.v("Staff may re-run OCR\nor correct OCR fields (rescore).\nCorrections do not verify the document.", 1100, 360, 340, 90, "note")
    p.e(per, ocr_fix, "Optional", exit="e", entry="w", dashed=True, color=INFO)

    allq = p.v("All required documents\nhave status verified?", 630, 500, 320, 110, "decision")
    p.e(v_ok, allq, exit="e", entry="w", color=YES)
    wait = p.v("Keep working the queue", 1100, 520, 280, 56, "process")
    p.e(allq, wait, "No", exit="e", entry="w", color=NO)
    p.e(wait, per, dashed=True, color="#94A3B8")

    done_v = p.v(
        "POST complete-verification\nstatus → under_evaluation\nAssign evaluation staff\nNotify applicant",
        610,
        660,
        360,
        90,
        "system",
    )
    st_eval = p.v("under_evaluation", 700, 780, 180, 36, "status")
    p.e(allq, done_v, "Yes", color=YES)
    p.e(done_v, st_eval)

    p.v("B. ELIGIBILITY EVALUATION  ·  permission applications.evaluate  ·  queue: under_evaluation", 40, 1120, 1600, 900, "phase")
    evq = p.v("Staff opens evaluation queue\nMay POST scan-eligibility\n(OCR any docs still missing text)", 610, 1180, 380, 80, "staff")
    engine = p.v(
        "EligibilityAssessmentService.assess()\nRules: contains / equals / not_contains / min / max / present\nModes: ocr (document corpus) or manual\nPrior-assistance academic year uses 1 June boundary",
        580,
        1290,
        440,
        110,
        "system",
    )
    agg = p.v("Aggregate result?\neligible · not_eligible · review\nno_ocr · no_rules", 630, 1430, 340, 110, "decision")
    rec = p.v(
        "POST evaluate  (remarks required)\neligibility_passed, per-rule checks,\ndocuments_complete, assessment,\nrecommended_amount\nrecommendation: approval | rejection | revision",
        580,
        1580,
        440,
        120,
        "staff",
    )
    rec_q = p.v("Recommendation?", 650, 1740, 300, 100, "decision")
    rec_rev = p.v("for_revision\nNotify applicant to fix", 120, 1750, 280, 64, "status_warn")
    fwd = p.v("Auto forwardToApproval\nstatus → for_approval\nAssign approval staff", 1100, 1740, 320, 80, "system")
    p.e(st_eval, evq)
    p.e(evq, engine)
    p.e(engine, agg)
    p.e(agg, rec, "Staff records judgement", color=INFO)
    p.e(rec, rec_q)
    p.e(rec_q, rec_rev, "revision", exit="w", entry="e", color=LOOP)
    p.e(rec_rev, loop, "Applicant returns", exit="s", entry="n", color=LOOP, dashed=True)
    p.e(rec_q, fwd, "approval or rejection", exit="e", entry="w", color=YES)
    st_fap = p.v("for_approval", 1170, 1850, 160, 36, "status")
    p.e(fwd, st_fap)

    p.v("C. FINAL APPROVAL  ·  permission applications.approve  ·  queue: for_approval", 40, 2050, 1600, 620, "phase")
    apq = p.v("Approving officer opens queue\nPOST decide", 630, 2110, 340, 64, "staff")
    dec = p.v("Final decision?", 650, 2210, 300, 100, "decision")
    d_rev = p.v("revision → for_revision", 120, 2220, 280, 50, "status_warn")
    d_rej = p.v("rejected  (terminal)", 120, 2300, 280, 40, "status_bad")
    d_ok = p.v(
        "approved\nStore approved_amount\nApplicationApprovedMail\nIf still pending_account, issue password",
        1100,
        2200,
        360,
        100,
        "staff",
    )
    d_end = p.v("END rejected", 170, 2370, 180, 50, "end_no")
    st_ap = p.v("approved", 1230, 2330, 140, 36, "status_ok")
    p.e(st_fap, apq)
    p.e(apq, dec)
    p.e(dec, d_rev, "revision", exit="w", entry="e", color=LOOP)
    p.e(d_rev, loop, dashed=True, color=LOOP)
    p.e(dec, d_rej, "rejected", exit="w", entry="e", color=NO)
    p.e(d_rej, d_end)
    p.e(dec, d_ok, "approved", exit="e", entry="w", color=YES)
    p.e(d_ok, st_ap)

    p.v("D. RELEASE & CLAIM  ·  permissions releases.manage / releases.verify", 40, 2700, 1600, 960, "phase")
    sch = p.v(
        "POST releases.schedule\nFrom approved or scheduled_for_release\nMethod: cash · bank_transfer · ewallet · check · other",
        580,
        2760,
        440,
        90,
        "staff",
    )
    sch_st = p.v("scheduled_for_release", 690, 2880, 220, 36, "status_ok")
    sch_mail = p.v("ReleaseScheduledMail\n(also used on reschedule)", 1100, 2760, 320, 70, "mail")
    rec = p.v(
        "POST releases.record\nCreate AssistanceRelease\nreference REL-… + verification_code (QR)",
        580,
        2950,
        440,
        80,
        "staff",
    )
    rel_st = p.v("released", 730, 3060, 140, 36, "status_ok")
    claim = p.v(
        "POST releases.verify\nLookup by application_no,\nreference_no, or QR verification_code",
        580,
        3130,
        440,
        80,
        "staff",
    )
    valid = p.v("Claim valid?", 650, 3250, 300, 90, "decision")
    bad = p.v("Show mismatch / not found", 160, 3260, 280, 50, "process")
    done = p.v("Mark application completed\nReleaseVerification recorded", 1100, 3250, 340, 70, "system")
    done_st = p.v("completed", 1220, 3350, 140, 36, "status_ok")
    end = p.v("END", 1230, 3420, 140, 50, "end_ok")
    p.e(st_ap, sch)
    p.e(sch, sch_mail, exit="e", entry="w", dashed=True, color="#BE185D")
    p.e(sch, sch_st)
    p.e(sch_st, rec)
    p.e(rec, rel_st)
    p.e(rel_st, claim)
    p.e(claim, valid)
    p.e(valid, bad, "No", exit="w", entry="e", color=NO)
    p.e(valid, done, "Yes", exit="e", entry="w", color=YES)
    p.e(done, done_st)
    p.e(done_st, end)

    p.v(
        "Reschedule: admin.releases.reschedule or reschedule-many. Manual assign: PUT admin.applications.assign. Destroy application: administrator only. cancel() exists in ApplicationService but has no HTTP route.",
        80,
        3580,
        1520,
        56,
        "note",
    )
    return p


def page_status() -> Page:
    p = Page("5. Status Map, OCR & Roles", 2000, 1780)
    p.v("  5. Application status map, OCR scoring, roles, and supporting modules", 0, 0, 2000, 56, "banner")

    # Status network
    p.v("APPLICATION STATUS LIFECYCLE", 40, 80, 1920, 36, "stage")

    d = p.v("draft", 80, 150, 140, 44, "status")
    acc = p.v("accepted", 280, 150, 140, 44, "status_ok")
    sub = p.v("submitted", 480, 150, 140, 44, "status")
    uv = p.v("under_verification", 680, 150, 180, 44, "status")
    ue = p.v("under_evaluation", 920, 150, 170, 44, "status")
    fa = p.v("for_approval", 1150, 150, 150, 44, "status")
    ap = p.v("approved", 1360, 150, 140, 44, "status_ok")
    sch = p.v("scheduled_for_release", 1540, 150, 200, 44, "status_ok")
    rel = p.v("released", 1780, 150, 140, 44, "status_ok")
    com = p.v("completed", 1780, 250, 140, 44, "status_ok")

    inc = p.v("incomplete", 680, 280, 150, 44, "status_warn")
    fr = p.v("for_revision", 920, 280, 150, 44, "status_warn")
    rej = p.v("rejected", 1150, 280, 140, 44, "status_bad")
    can = p.v("cancelled", 80, 280, 140, 44, "status_bad")

    p.e(d, acc, "staff accept", exit="e", entry="w", color=YES)
    p.e(d, rej, "staff reject", exit="s", entry="w", color=NO, dashed=True)
    p.e(acc, sub, "applicant submit", exit="e", entry="w")
    p.e(sub, uv, "first verify action\nor re-submit", exit="e", entry="w")
    p.e(uv, ue, "all docs verified", exit="e", entry="w", color=YES)
    p.e(ue, fa, "recommend approval\nor rejection", exit="e", entry="w")
    p.e(fa, ap, "decide approved", exit="e", entry="w", color=YES)
    p.e(ap, sch, "schedule", exit="e", entry="w", color=YES)
    p.e(sch, rel, "record release", exit="e", entry="w", color=YES)
    p.e(rel, com, "verify claim", exit="s", entry="n", color=YES)

    p.e(uv, inc, "reject a document", exit="s", entry="n", color=LOOP)
    p.e(uv, fr, "request revision", exit="s", entry="w", color=LOOP)
    p.e(inc, uv, "re-upload / submit", exit="n", entry="s", color=LOOP, dashed=True)
    p.e(fr, uv, "re-upload / submit", exit="n", entry="s", color=LOOP, dashed=True)
    p.e(ue, fr, "evaluate → revision", exit="s", entry="n", color=LOOP)
    p.e(fa, fr, "decide revision", exit="s", entry="n", color=LOOP)
    p.e(fa, rej, "decide rejected", exit="s", entry="n", color=NO)
    p.e(d, can, "internal cancel()", exit="s", entry="n", color=NO, dashed=True)

    p.v(
        "Document statuses: pending → verified | rejected | revision_requested\n"
        "Public timeline (happy path): submitted → under_verification → under_evaluation → for_approval → approved → scheduled_for_release → released → completed",
        40,
        360,
        1920,
        56,
        "legend",
    )

    p.v(
        "OCR PIPELINE (DocumentOcrService)\n"
        "1. Applicant (or staff) uploads / scans a file\n"
        "2. OCR.space returns raw_text (OCRSPACE_API_KEY)\n"
        "3. Expected type from requirement name vs detected type\n"
        "4. Extracted fields compared with applicant, profile, address, answers\n"
        "5. overall_status: matched | mismatch | review | type_mismatch | failed | extracted\n"
        "6. Staff can correct fields and rescore; that is not the same as verifying the document\n"
        "OCR corpus for eligibility excludes rejected and revision_requested files",
        40,
        440,
        630,
        220,
        "card",
    )
    p.v(
        "ELIGIBILITY RULES (EligibilityAssessmentService)\n"
        "Operators: contains, equals, not_contains, min, max, present\n"
        "Check modes: ocr (scan documents) or manual (staff tick)\n"
        "Typical seeded rules: resident of Nabua, currently enrolled,\n"
        "tuition assessment present, no duplicate award this academic year\n"
        "Manual examples: prior assistance, school outside barangay, family relationship\n"
        "Result is per-rule passed / failed / review — not a single application score\n"
        "Evaluation still requires a human recommendation",
        690,
        440,
        640,
        220,
        "card",
    )
    p.v(
        "PERMISSIONS (staff modules)\n"
        "dashboard.view\n"
        "applicants.view / manage\n"
        "applications.view / manage / verify / evaluate / approve\n"
        "programs.view / manage\n"
        "releases.view / manage / verify\n"
        "reports.view\n"
        "announcements.view / manage\n"
        "users.view / manage · roles.manage\n"
        "audit.view · settings.view / manage",
        1350,
        440,
        610,
        220,
        "card",
    )

    p.v(
        "IN-APP NOTIFICATIONS (no email)\n"
        "Submitted · document verified · evaluated · approved/rejected · release recorded · OCR type mismatch · revision / incomplete",
        40,
        680,
        1920,
        50,
        "legend",
    )

    p.v(
        "DATA STORED WITH AN APPLICATION\n"
        "ApplicationAnswer · DocumentSubmission → DocumentVerification + OcrResult → OcrExtractedField\n"
        "ApplicationStatusHistory · ApplicationEvaluation (eligibility_checks JSON) · ApplicationApproval\n"
        "ReleaseSchedule · AssistanceRelease → ReleaseVerification\n"
        "assigned_staff_id (User)  ·  WorkflowStaff (step + user)  ·  SystemNotification  ·  AuditLog",
        40,
        750,
        960,
        160,
        "card",
    )
    p.v(
        "SECURITY & GATES\n"
        "Turnstile session 30 minutes; OTP cache apply-otp:{email}\n"
        "Throttles: turnstile 20/min · OTP 5/min · submit 10/min\n"
        "Middleware: staff (not applicant) · applicant (must have Applicant row)\n"
        "permission:{slug} on admin routes · ApplicationPolicy for verify/evaluate/approve\n"
        "Passwords for pending public applicants are issued only after accept or final approve",
        1020,
        750,
        940,
        160,
        "card",
    )

    p.v(
        "STACK: Laravel 12 + Inertia.js + Vue 3  ·  Ziggy routes  ·  MySQL/SQLite  ·  OCR.space  ·  Cloudflare Turnstile\n"
        "Config: config/cams.php (agency, barangays, release methods)  ·  SystemSetting overrides agency display fields",
        40,
        930,
        1920,
        56,
        "note",
    )
    return p


def main() -> None:
    pages = [
        ("overview", page_overview()),
        ("intake", page_intake()),
        ("documents", page_documents()),
        ("staff", page_staff()),
        ("status", page_status()),
    ]
    inner = "\n".join(page.xml(did) for did, page in pages)
    xml = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<mxfile host="app.diagrams.net" agent="CAMS flowchart generator" version="22.1.0" type="device">\n'
        f"{inner}\n"
        "</mxfile>\n"
    )
    with open(OUT, "w", encoding="utf-8") as f:
        f.write(xml)
    print(f"Wrote {OUT} ({len(xml):,} bytes, {len(pages)} pages)")


if __name__ == "__main__":
    main()
