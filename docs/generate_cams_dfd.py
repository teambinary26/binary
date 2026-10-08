"""Generate LYDO CAMS data-flow diagrams as separate draw.io files.

Level 0, Level 1, and Level 2 each get their own file. Every connector is a
straight line on its own lane, so lines do not cross each other or cut
through a box.
"""

from __future__ import annotations

from pathlib import Path
from xml.sax.saxutils import escape as xml_escape

ROOT = Path(r"C:\Users\John Lloyd\Desktop\NEA\docs\dfd")

STRAIGHT = (
    "rounded=0;html=1;strokeWidth=1.7;endArrow=blockThin;endFill=1;startArrow=none;"
    "fontSize=12;fontStyle=1;fontColor=#0F172A;labelBackgroundColor=#FFFFFF;"
    "exitDx=0;exitDy=0;entryDx=0;entryDy=0;"
)

NAVY = "#1B365D"
TEAL = "#0F766E"
PURPLE = "#6D28D9"
STAFF = "#B45309"

PITCH = 46
BOX_GAP = 20
LANE = 280
STAGGER = PITCH / 2
MARGIN = 40
LEFT_W = 270
RIGHT_W = 320
CENTER_W = 380

try:
    from PIL import ImageFont

    _FONT = ImageFont.truetype(r"C:\Windows\Fonts\arialbd.ttf", 12)
except Exception:  # pragma: no cover - Windows font is present on this machine
    class _FallbackFont:
        @staticmethod
        def getlength(text: str) -> float:
            return len(text) * 7.4

    _FONT = _FallbackFont()


def text_w(text: str) -> float:
    return float(_FONT.getlength(text))

STYLES = {
    "banner": (
        "rounded=0;whiteSpace=wrap;html=1;fillColor=#1B365D;strokeColor=none;"
        "fontColor=#FFFFFF;fontStyle=1;fontSize=18;align=left;spacingLeft=18;"
    ),
    "subtitle": (
        "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;"
        "fontSize=12;fontColor=#475569;"
    ),
    "heading": (
        "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;"
        "fontSize=15;fontStyle=1;fontColor=#1B365D;"
    ),
    "process": (
        "rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#E8F1F8;strokeColor=#1B4F72;"
        "strokeWidth=2;fontSize=14;fontColor=#0F172A;verticalAlign=middle;align=center;"
    ),
    "context": (
        "rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#1B365D;fontColor=#FFFFFF;"
        "strokeColor=#0F2444;strokeWidth=2;fontSize=18;fontStyle=1;shadow=1;verticalAlign=middle;"
    ),
    "entity": (
        "rounded=0;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#1B365D;"
        "strokeWidth=2;fontSize=13;fontStyle=1;fontColor=#1B365D;verticalAlign=middle;"
    ),
    "entity_staff": (
        "rounded=0;whiteSpace=wrap;html=1;fillColor=#FFFBEB;strokeColor=#B45309;"
        "strokeWidth=2;fontSize=13;fontStyle=1;fontColor=#92400E;verticalAlign=middle;"
    ),
    "entity_admin": (
        "rounded=0;whiteSpace=wrap;html=1;fillColor=#F5F3FF;strokeColor=#6D28D9;"
        "strokeWidth=2;fontSize=13;fontStyle=1;fontColor=#5B21B6;verticalAlign=middle;"
    ),
    "external": (
        "rounded=0;whiteSpace=wrap;html=1;fillColor=#FDF4FF;strokeColor=#7E22CE;"
        "strokeWidth=2;fontSize=13;fontStyle=1;fontColor=#6B21A8;verticalAlign=middle;"
    ),
    "store": (
        "shape=partialRectangle;whiteSpace=wrap;html=1;left=1;right=0;top=1;bottom=1;"
        "fillColor=#F0FDFA;strokeColor=#0F766E;strokeWidth=2;align=center;"
        "verticalAlign=middle;fontSize=13;fontColor=#134E4A;fontStyle=1;"
    ),
    "legend": (
        "rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#FFFFFF;strokeColor=#CBD5E1;"
        "align=left;spacingLeft=12;spacingTop=8;verticalAlign=top;fontSize=12;fontColor=#0F172A;"
    ),
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
        self.geom: dict[str, tuple[float, float, float, float]] = {}
        self.lines: list[tuple[float, float, float, float, str, str]] = []
        self.labels: list[tuple[float, float, float, float, str]] = []

    def _id(self) -> str:
        i = str(self._n)
        self._n += 1
        return i

    def v(self, text: str, x: float, y: float, w: float, h: float, kind: str) -> str:
        i = self._id()
        self.geom[i] = (x, y, w, h)
        self.nodes.append(
            f'<mxCell id="{i}" value="{val(text)}" style="{STYLES[kind]}" vertex="1" parent="1">'
            f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/></mxCell>'
        )
        return i

    def e(
        self,
        source: str,
        target: str,
        label: str,
        exit: str,
        entry: str,
        color: str,
        exit_at: float,
        entry_at: float,
        oy: float = -14,
    ) -> None:
        i = self._id()
        sides = {"s": (exit_at, 1), "n": (exit_at, 0), "e": (1, exit_at), "w": (0, exit_at)}
        entries = {"s": (entry_at, 1), "n": (entry_at, 0), "e": (1, entry_at), "w": (0, entry_at)}
        ex, ey = sides[exit]
        enx, eny = entries[entry]
        style = (
            f"{STRAIGHT}strokeColor={color};"
            f"exitX={ex};exitY={ey};entryX={enx};entryY={eny};"
        )
        geom = (
            '<mxGeometry relative="1" as="geometry">'
            f'<mxPoint as="offset" x="0" y="{oy}"/>'
            "</mxGeometry>"
        )
        self.edges.append(
            f'<mxCell id="{i}" value="{val(label)}" style="{style}" edge="1" parent="1" '
            f'source="{source}" target="{target}">{geom}</mxCell>'
        )

    def hflow(self, src: str, dst: str, label: str, y: float, color: str) -> None:
        sx, sy, sw, sh = self.geom[src]
        tx, ty, tw, th = self.geom[dst]
        if sx + sw <= tx + 1:
            exit_side, entry_side = "e", "w"
            x1, x2 = sx + sw, tx
        elif tx + tw <= sx + 1:
            exit_side, entry_side = "w", "e"
            x1, x2 = sx, tx + tw
        else:
            raise SystemExit(f"Horizontal boxes overlap for '{label}'")
        exit_at = (y - sy) / sh
        entry_at = (y - ty) / th
        if not (0.04 <= exit_at <= 0.96 and 0.04 <= entry_at <= 0.96):
            raise SystemExit(
                f"Port outside a box for '{label}' ({exit_at:.2f}, {entry_at:.2f})"
            )
        self.e(src, dst, label, exit_side, entry_side, color, exit_at, entry_at)
        self.lines.append((x1, y, x2, y, src, dst))
        self._note_label(x1, y, x2, y, label, -14)

    def vflow(self, src: str, dst: str, label: str, x: float, color: str, oy: float = -14) -> None:
        sx, sy, sw, sh = self.geom[src]
        tx, ty, tw, th = self.geom[dst]
        if sy + sh <= ty + 1:
            exit_side, entry_side = "s", "n"
            y1, y2 = sy + sh, ty
        elif ty + th <= sy + 1:
            exit_side, entry_side = "n", "s"
            y1, y2 = sy, ty + th
        else:
            raise SystemExit(f"Vertical boxes overlap for '{label}'")
        exit_at = (x - sx) / sw
        entry_at = (x - tx) / tw
        if not (0.04 <= exit_at <= 0.96 and 0.04 <= entry_at <= 0.96):
            raise SystemExit(
                f"Port outside a box for '{label}' ({exit_at:.2f}, {entry_at:.2f})"
            )
        self.e(src, dst, label, exit_side, entry_side, color, exit_at, entry_at, oy=oy)
        self.lines.append((x, y1, x, y2, src, dst))
        self._note_label(x, y1, x, y2, label, oy)

    def _note_label(self, x1: float, y1: float, x2: float, y2: float, label: str, oy: float) -> None:
        if not label:
            return
        width = text_w(label)
        height = 16
        cx = (x1 + x2) / 2
        cy = (y1 + y2) / 2 + oy
        self.labels.append((cx - width / 2, cy - height / 2, width, height, label))

    def overlaps(self) -> list[str]:
        items = list(self.geom.items())
        hits: list[str] = []
        for i, (a, box_a) in enumerate(items):
            ax, ay, aw, ah = box_a
            if ah <= 40:
                continue
            for b, box_b in items[i + 1 :]:
                bx, by, bw, bh = box_b
                if bh <= 40:
                    continue
                if ax + aw <= bx + 1 or bx + bw <= ax + 1 or ay + ah <= by + 1 or by + bh <= ay + 1:
                    continue
                hits.append(f"{a} overlaps {b}")
        return hits

    def line_problems(self) -> list[str]:
        problems: list[str] = []
        for i, (x1, y1, x2, y2, src, dst) in enumerate(self.lines):
            horizontal = abs(y1 - y2) < 1
            if horizontal:
                lo, hi = sorted((x1, x2))
                lo, hi = lo + 4, hi - 4
                for other, (ox, oy, ow, oh) in self.geom.items():
                    if other in (src, dst) or oh <= 40:
                        continue
                    if oy + 3 < y1 < oy + oh - 3 and ox < hi and ox + ow > lo:
                        problems.append(f"line through box {other} at y={y1:.0f}")
            else:
                lo, hi = sorted((y1, y2))
                lo, hi = lo + 4, hi - 4
                for other, (ox, oy, ow, oh) in self.geom.items():
                    if other in (src, dst) or oh <= 40:
                        continue
                    if ox + 3 < x1 < ox + ow - 3 and oy < hi and oy + oh > lo:
                        problems.append(f"line through box {other} at x={x1:.0f}")
            for x3, y3, x4, y4, _, _ in self.lines[i + 1 :]:
                other_h = abs(y3 - y4) < 1
                if horizontal and other_h and abs(y1 - y3) < 20:
                    a1, a2 = sorted((x1, x2))
                    b1, b2 = sorted((x3, x4))
                    if a1 < b2 - 4 and b1 < a2 - 4:
                        problems.append(f"horizontal lines share a lane at y={y1:.0f}")
                if not horizontal and not other_h and abs(x1 - x3) < 18:
                    a1, a2 = sorted((y1, y2))
                    b1, b2 = sorted((y3, y4))
                    if a1 < b2 - 4 and b1 < a2 - 4:
                        problems.append(f"vertical lines share a lane at x={x1:.0f}")
        boxes = [(gid, box) for gid, box in self.geom.items() if box[3] > 40]
        for x, y, w, h, label in self.labels:
            for _, (bx, by, bw, bh) in boxes:
                if x < bx + bw - 1 and x + w > bx + 1 and y < by + bh - 1 and y + h > by + 1:
                    problems.append(f"label '{label}' overlaps a box")
                    break
        for i, (x, y, w, h, label) in enumerate(self.labels):
            for ox, oy, ow, oh, other in self.labels[i + 1 :]:
                if x < ox + ow - 1 and x + w > ox + 1 and y < oy + oh - 1 and y + h > oy + 1:
                    problems.append(f"labels '{label}' and '{other}' overlap")
        return problems

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


def banner(page: Page, title: str, subtitle: str) -> None:
    page.v(title, 0, 0, page.width, 52, "banner")
    page.v(subtitle, 28, 58, page.width - 56, 34, "subtitle")


def finish(page: Page, bottom: float, legend: str) -> None:
    lines = legend.count("\n") + 1
    height = 20 + lines * 18
    page.v(legend, MARGIN, bottom + 36, page.width - MARGIN * 2, height, "legend")
    page.height = int(bottom + 36 + height + 28)


def band_page_width(center_w: float = CENTER_W) -> int:
    return int(MARGIN + LEFT_W + LANE + center_w + LANE + RIGHT_W + MARGIN)


def stack_side(items: list, x: float, width: float) -> tuple[list, list, float]:
    """Return boxes and ports with y measured from 0, plus the stack height."""
    boxes = []
    ports = []
    y = 0.0
    for index, (label, kind, flows) in enumerate(items):
        height = len(flows) * PITCH + 8
        boxes.append((label, kind, x, y, width, height))
        for flow_index, (direction, flow_label, color) in enumerate(flows):
            ports.append((index, y + PITCH / 2 + 4 + flow_index * PITCH, direction, flow_label, color))
        y += height
        if index != len(items) - 1:
            y += BOX_GAP
    return boxes, ports, y


def _place(boxes: list, ports: list, shift: float) -> tuple[list, list]:
    moved_boxes = [(label, kind, x, y + shift, width, height) for label, kind, x, y, width, height in boxes]
    moved_ports = [(index, y + shift, direction, label, color) for index, y, direction, label, color in ports]
    return moved_boxes, moved_ports


def process_band(
    page: Page,
    top: float,
    heading: str,
    center_text: str,
    left: list,
    right: list,
    *,
    center_kind: str = "process",
    left_w: float = LEFT_W,
    center_w: float = CENTER_W,
    right_w: float = RIGHT_W,
    gap: float = LANE,
) -> float:
    if heading:
        page.v(heading, MARGIN, top, page.width - MARGIN * 2, 26, "heading")
        top += 36
    left_x = MARGIN
    center_x = left_x + left_w + gap
    right_x = center_x + center_w + gap
    left_boxes, left_ports, left_h = stack_side(left, left_x, left_w)
    right_boxes, right_ports, right_h = stack_side(right, right_x, right_w)
    if left_h + 8 < right_h:
        span = right_h + STAGGER
        right_shift = STAGGER
        # Snap onto the pitch grid so a left line never lines up with a right line.
        target = STAGGER + (right_h - left_h) / 2
        left_shift = round(target / PITCH) * PITCH
        if left_shift + left_h > span:
            left_shift = max(0, round((span - left_h) / PITCH) * PITCH)
    elif right_h + 8 < left_h:
        span = left_h
        left_shift = 0
        base = (left_h - right_h) / 2
        delta = STAGGER - (base % PITCH)
        if delta > PITCH / 2:
            delta -= PITCH
        if delta < -PITCH / 2:
            delta += PITCH
        right_shift = max(0, base + delta)
        span = max(span, right_shift + right_h)
    else:
        span = max(left_h, right_h + STAGGER)
        left_shift = 0
        right_shift = STAGGER
    left_boxes, left_ports = _place(left_boxes, left_ports, top + left_shift)
    right_boxes, right_ports = _place(right_boxes, right_ports, top + right_shift)
    bottom = top + span
    center = page.v(center_text, center_x, top, center_w, span, center_kind)
    ids: list[str] = []
    for label, kind, x, y, width, height in left_boxes + right_boxes:
        ids.append(page.v(label, x, y, width, height, kind))
    left_count = len(left_boxes)
    for index, y, direction, label, color in left_ports:
        box = ids[index]
        src, dst = (box, center) if direction == "in" else (center, box)
        page.hflow(src, dst, label, y, color)
    for index, y, direction, label, color in right_ports:
        box = ids[left_count + index]
        src, dst = (box, center) if direction == "in" else (center, box)
        page.hflow(src, dst, label, y, color)
    return bottom


# Flow tuples: ("in" to the process, or "out" from the process, label, color)
IN = "in"
OUT = "out"


def page_context() -> Page:
    page = Page("Context diagram", band_page_width(460), 900)
    banner(
        page,
        "  LYDO Nabua  ·  CAMS data-flow diagram  ·  Level 0",
        "Community Assistance Management System  ·  Municipality of Nabua, Camarines Sur. Each line is one kind of data, on its own lane.",
    )
    bottom = process_band(
        page,
        110,
        "",
        "0\nLYDO CAMS\n\nCommunity Assistance\nManagement System",
        [
            (
                "Citizen / Applicant",
                "entity",
                [
                    (IN, "Registration & login", NAVY),
                    (IN, "Application data & documents", NAVY),
                    (IN, "Application number", NAVY),
                    (OUT, "Programs & announcements", NAVY),
                    (OUT, "Status & notifications", NAVY),
                ],
            ),
            (
                "LYDO Staff",
                "entity_staff",
                [
                    (IN, "Verification & approval", STAFF),
                    (IN, "Release & claim lookup", STAFF),
                    (OUT, "Queues & OCR scores", STAFF),
                ],
            ),
            (
                "Administrator",
                "entity_admin",
                [
                    (IN, "Programs, users & settings", PURPLE),
                    (OUT, "Dashboard & reports", PURPLE),
                ],
            ),
        ],
        [
            (
                "Cloudflare Turnstile",
                "external",
                [
                    (OUT, "Turnstile token", PURPLE),
                    (IN, "Pass / fail", PURPLE),
                ],
            ),
            (
                "Google Sign-In",
                "external",
                [
                    (OUT, "Sign-in request", PURPLE),
                    (IN, "Verified email", PURPLE),
                ],
            ),
            ("Email service", "external", [(OUT, "OTP & status emails", PURPLE)]),
            (
                "OCR.space",
                "external",
                [
                    (OUT, "Document image", PURPLE),
                    (IN, "Parsed text", PURPLE),
                ],
            ),
            ("Semaphore SMS", "external", [(OUT, "Release SMS", PURPLE)]),
        ],
        center_kind="context",
        center_w=460,
    )
    finish(
        page,
        bottom,
        "Squares are people and outside services. The navy box is the whole system.\n"
        "Lines run straight, each on its own lane, so they do not cross.\n"
        "Level 1 splits CAMS into processes. Level 2 finishes every process. Both are separate files in this folder.\n"
        "A name marked * on a later page is the same person, service, or store drawn again.",
    )
    return page


def level1_page(name: str, title: str, subtitle: str, bands: list, legend: str) -> Page:
    page = Page(name, band_page_width(), 800)
    banner(page, title, subtitle)
    top = 108
    bottom = top
    for heading, center, left, right in bands:
        bottom = process_band(page, top, heading, center, left, right)
        top = bottom + 48
    finish(page, bottom, legend)
    return page


def page_level1_intake() -> Page:
    return level1_page(
        "Intake",
        "  Level 1 data flow  ·  Accounts, catalogue, and application intake",
        "Processes 1.0, 2.0 and 3.0. People are on the left. Data stores and the result of each process are on the right.",
        [
            (
                "1.0  Authenticate and register accounts",
                "1.0\nAuthenticate and\nregister accounts",
                [
                    (
                        "Citizen / Applicant",
                        "entity",
                        [
                            (IN, "Login credentials", NAVY),
                            (IN, "Registration details", NAVY),
                            (OUT, "Session", NAVY),
                        ],
                    ),
                    ("Google *", "external", [(IN, "Verified email", PURPLE)]),
                    (
                        "Turnstile *",
                        "external",
                        [
                            (OUT, "Login token", PURPLE),
                            (IN, "Pass / fail", PURPLE),
                        ],
                    ),
                    ("Administrator", "entity_admin", [(IN, "Users & roles", PURPLE)]),
                ],
                [
                    (
                        "D1   Users & roles",
                        "store",
                        [
                            (OUT, "User account", TEAL),
                            (IN, "Role & permissions", TEAL),
                        ],
                    ),
                    ("D2   Profiles & addresses", "store", [(OUT, "Profile & address", TEAL)]),
                ],
            ),
            (
                "2.0  Publish programs and announcements",
                "2.0\nPublish programs and\nannouncements",
                [
                    (
                        "Administrator",
                        "entity_admin",
                        [
                            (IN, "Program & rules", PURPLE),
                            (IN, "Announcement text", PURPLE),
                            (IN, "Settings & workflow", PURPLE),
                        ],
                    ),
                    ("Citizen / Applicant *", "entity", [(OUT, "Programs & announcements", NAVY)]),
                ],
                [
                    ("D3   Programs & rules", "store", [(OUT, "Catalogue & rules", TEAL)]),
                    ("D7   Announcements", "store", [(OUT, "Announcement", TEAL)]),
                    ("D10  Settings & workflow", "store", [(OUT, "Agency & workflow staff", TEAL)]),
                ],
            ),
            (
                "3.0  Capture and track applications",
                "3.0\nCapture and track\napplications",
                [
                    (
                        "Citizen / Applicant *",
                        "entity",
                        [
                            (IN, "Personal data & files", NAVY),
                            (IN, "Application number", NAVY),
                            (OUT, "Status & timeline", NAVY),
                        ],
                    ),
                    (
                        "Turnstile *",
                        "external",
                        [
                            (OUT, "Apply-form token", PURPLE),
                            (IN, "Pass / fail", PURPLE),
                        ],
                    ),
                    ("Email *", "external", [(OUT, "OTP & received mail", PURPLE)]),
                ],
                [
                    ("D3 *  Programs & rules", "store", [(IN, "Open program & fields", TEAL)]),
                    ("D1 *  Users & roles", "store", [(OUT, "Pending account", TEAL)]),
                    ("D2 *  Profiles", "store", [(OUT, "Applicant profile", TEAL)]),
                    (
                        "D4   Applications",
                        "store",
                        [
                            (OUT, "Application & answers", TEAL),
                            (IN, "Status record", TEAL),
                        ],
                    ),
                    ("D5   Documents & files", "store", [(OUT, "ID & requirement files", TEAL)]),
                    ("D8   Notifications", "store", [(OUT, "In-app notice", TEAL)]),
                ],
            ),
        ],
        "D1 is users and roles. D2 is applicant profiles and addresses. D3 is programs, requirements and rules.\n"
        "D4 is the application, answers and status. D5 is uploaded files. D7 is announcements. D8 is in-app notices. D10 is settings.\n"
        "A public application creates a pending user. The real password is emailed only when staff accept the draft or approve it.\n"
        "* means the same person, service, or store shown again so the line can stay on this row.",
    )


def page_level1_office() -> Page:
    return level1_page(
        "Office",
        "  Level 1 data flow  ·  Verification, decision, release, and reporting",
        "Processes 4.0 to 8.0. Staff actions enter on the left. Stores and outside services sit on the right of the process that uses them.",
        [
            (
                "4.0  Verify documents",
                "4.0\nVerify documents",
                [("LYDO Staff", "entity_staff", [(IN, "Verify, reject or revision", STAFF)])],
                [
                    (
                        "OCR.space *",
                        "external",
                        [
                            (OUT, "Document image", PURPLE),
                            (IN, "Parsed text", PURPLE),
                        ],
                    ),
                    (
                        "D5   Documents & files",
                        "store",
                        [
                            (IN, "Document file", TEAL),
                            (OUT, "OCR result & verification", TEAL),
                        ],
                    ),
                    ("D2 *  Profiles", "store", [(IN, "Profile to match", TEAL)]),
                    ("D4 *  Applications", "store", [(OUT, "Verification status", TEAL)]),
                    ("Email *", "external", [(OUT, "Revision request", PURPLE)]),
                    ("D8 *  Notifications", "store", [(OUT, "Document notice", TEAL)]),
                ],
            ),
            (
                "5.0  Assess eligibility",
                "5.0\nAssess eligibility",
                [("LYDO Staff", "entity_staff", [(IN, "Evaluation & manual checks", STAFF)])],
                [
                    ("D3 *  Programs & rules", "store", [(IN, "Eligibility rules", TEAL)]),
                    ("D5 *  OCR fields", "store", [(IN, "OCR scores", TEAL)]),
                    (
                        "D4 *  Applications",
                        "store",
                        [
                            (IN, "Form answers", TEAL),
                            (OUT, "Evaluation result", TEAL),
                        ],
                    ),
                ],
            ),
            (
                "6.0  Approve or return applications",
                "6.0\nApprove or return\napplications",
                [
                    (
                        "LYDO Staff",
                        "entity_staff",
                        [(IN, "Accept, approve, reject or revision", STAFF)],
                    )
                ],
                [
                    (
                        "D4 *  Applications",
                        "store",
                        [
                            (IN, "Application & evaluation", TEAL),
                            (OUT, "Approval & new status", TEAL),
                        ],
                    ),
                    ("D1 *  Users & roles", "store", [(OUT, "Active account & password", TEAL)]),
                    ("Email *", "external", [(OUT, "Acceptance & approval mail", PURPLE)]),
                    ("D8 *  Notifications", "store", [(OUT, "Decision notice", TEAL)]),
                ],
            ),
            (
                "7.0  Release assistance",
                "7.0\nRelease assistance",
                [
                    (
                        "LYDO Staff",
                        "entity_staff",
                        [
                            (IN, "Schedule & payout", STAFF),
                            (IN, "Claim reference or QR", STAFF),
                        ],
                    )
                ],
                [
                    (
                        "D4 *  Applications",
                        "store",
                        [
                            (IN, "Approved application", TEAL),
                            (OUT, "Release status", TEAL),
                        ],
                    ),
                    ("D6   Releases & claims", "store", [(OUT, "Schedule, payout & claim", TEAL)]),
                    ("Email *", "external", [(OUT, "Release mail", PURPLE)]),
                    ("Semaphore SMS *", "external", [(OUT, "Release SMS", PURPLE)]),
                    ("D8 *  Notifications", "store", [(OUT, "Release notice", TEAL)]),
                ],
            ),
            (
                "8.0  Produce reports and the audit trail",
                "8.0\nProduce reports and\nthe audit trail",
                [
                    (
                        "Administrator",
                        "entity_admin",
                        [
                            (IN, "Report filters", PURPLE),
                            (OUT, "Excel, PDF & CSV", PURPLE),
                        ],
                    ),
                    (
                        "LYDO Staff",
                        "entity_staff",
                        [
                            (IN, "Report filters", STAFF),
                            (OUT, "On-screen reports", STAFF),
                        ],
                    ),
                ],
                [
                    ("D2 *  Profiles", "store", [(IN, "Beneficiaries", TEAL)]),
                    ("D4 *  Applications", "store", [(IN, "Applications", TEAL)]),
                    ("D6 *  Releases", "store", [(IN, "Releases", TEAL)]),
                    ("D9   Audit log", "store", [(IN, "Audit events", TEAL)]),
                ],
            ),
        ],
        "Processes 4.0 to 7.0 also write an audit event to D9. Those repeated lines are left off this page. Process 8.0 is the one that reads the log.\n"
        "Reports are the application list (Excel or PDF), the financial summary (Excel or PDF), and beneficiaries by barangay (CSV).\n"
        "Staff can verify, evaluate, or approve only with that permission and a workflow-staff row. An administrator skips that check.\n"
        "Release methods are cash, bank, e-wallet, check, and other. A claim is matched on a reference, a QR code, or a CAMS number.",
    )


Flow = tuple[str, str, str]
Box = tuple[str, str, list[Flow]]


INNER_GAP = 16
ABOVE_H = 78
BELOW_H = 78
PROC_H = 118
V_GAP = 96


def _item_min_width(item: Box) -> float:
    label, _kind, flows = item
    name = text_w(label) + 36
    if len(flows) <= 1:
        flow = text_w(flows[0][1]) + 28 if flows else 0
        return max(176, name, flow)
    left = text_w(flows[0][1])
    right = text_w(flows[1][1])
    # Ports sit at 27% and 73% so the two labels stay inside this box.
    fit_left = (left / 2 + 12) / 0.27
    fit_right = (right / 2 + 12) / 0.27
    fit_pair = (left / 2 + right / 2 + 20) / 0.46
    return max(name, fit_left, fit_right, fit_pair)


def _row_min_width(items: list[Box]) -> float:
    if not items:
        return 0
    return sum(_item_min_width(item) for item in items) + INNER_GAP * (len(items) - 1)


def step_width(step: dict) -> float:
    return max(float(step.get("w", 0)), _row_min_width(step.get("above") or []), _row_min_width(step.get("below") or []), 230)


def link_gap(step: dict) -> float:
    labels = step.get("next") or []
    if isinstance(labels, str):
        labels = [labels]
    if not labels:
        return 110
    return max(150, max(text_w(label) for label in labels) + 64)


def row_pixel_width(steps: list[dict]) -> float:
    total = MARGIN
    for index, step in enumerate(steps):
        total += step_width(step)
        if index < len(steps) - 1:
            total += link_gap(step)
    return total + MARGIN


def draw_chain(page: Page, top: float, steps: list[dict]) -> float:
    has_above = any(step.get("above") for step in steps)
    has_below = any(step.get("below") for step in steps)
    above_y = top
    proc_y = top + (ABOVE_H + V_GAP if has_above else 0)
    below_y = proc_y + PROC_H + V_GAP if has_below else proc_y + PROC_H
    x = float(MARGIN)
    proc_ids: list[str] = []
    widths: list[float] = []
    for step in steps:
        width = step_width(step)
        widths.append(width)
        proc_ids.append(page.v(step["title"], x, proc_y, width, PROC_H, "process"))
        _attach_row(page, proc_ids[-1], step.get("above") or [], x, above_y, width, ABOVE_H)
        _attach_row(page, proc_ids[-1], step.get("below") or [], x, below_y, width, BELOW_H)
        x += width + link_gap(step)
    for index, step in enumerate(steps[:-1]):
        labels = step.get("next") or []
        if isinstance(labels, str):
            labels = [labels]
        for label_index, label in enumerate(labels):
            fraction = 0.5 if len(labels) == 1 else (0.34 if label_index == 0 else 0.66)
            page.hflow(proc_ids[index], proc_ids[index + 1], label, proc_y + PROC_H * fraction, "#334155")
    return below_y + (BELOW_H if has_below else 0)


def _attach_row(page: Page, process: str, items: list[Box], x: float, y: float, width: float, height: float) -> None:
    if not items:
        return
    mins = [_item_min_width(item) for item in items]
    extra = width - (sum(mins) + INNER_GAP * (len(items) - 1))
    share = extra / len(items) if extra > 0 else 0
    box_x = x
    for (label, kind, flows), box_w in zip(items, mins):
        box_w += share
        box = page.v(label, box_x, y, box_w, height, kind)
        for flow_index, (direction, flow_label, color) in enumerate(flows):
            at = 0.5 if len(flows) == 1 else (0.27 if flow_index == 0 else 0.73)
            line_x = box_x + box_w * at
            src, dst = (box, process) if direction == IN else (process, box)
            page.vflow(src, dst, flow_label, line_x, color, oy=-14)
        box_x += box_w + INNER_GAP


def chain_page(name: str, title: str, subtitle: str, rows: list[tuple[str, list[dict]]], legend: str) -> Page:
    width = 80
    for _, steps in rows:
        width = max(width, row_pixel_width(steps))
    page = Page(name, int(width), 800)
    banner(page, title, subtitle)
    top = 112.0
    bottom = top
    for heading, steps in rows:
        if heading:
            page.v(heading, 36, top, page.width - 72, 26, "heading")
            top += 34
        bottom = draw_chain(page, top, steps)
        top = bottom + 46
    finish(page, bottom, legend)
    return page


def page_l2_auth() -> Page:
    return chain_page(
        "1.0 Authenticate",
        "  Level 2 of process 1.0  ·  Authenticate and register accounts",
        "Login is the top row. Registering an applicant and maintaining staff accounts are the bottom row. They all use D1.",
        [
            (
                "Sign in",
                [
                    {
                        "title": "1.1\nCheck Turnstile",
                        "above": [
                            (
                                "Turnstile *",
                                "external",
                                [
                                    (OUT, "Login token", PURPLE),
                                    (IN, "Pass / fail", PURPLE),
                                ],
                            )
                        ],
                        "next": "Login allowed",
                    },
                    {
                        "title": "1.2\nSign in",
                        "w": 320,
                        "above": [
                            ("Citizen", "entity", [(IN, "Login credentials", NAVY)]),
                            ("Google *", "external", [(IN, "Verified email", PURPLE)]),
                        ],
                        "below": [("D1  Users & roles", "store", [(IN, "Role & permissions", TEAL)])],
                        "next": "Authenticated user",
                    },
                    {
                        "title": "1.5\nStart session",
                        "above": [("Citizen *", "entity", [(OUT, "Session", NAVY)])],
                        "below": [("D9  Audit log", "store", [(OUT, "Login audit", TEAL)])],
                    },
                ],
            ),
            (
                "Create accounts",
                [
                    {
                        "title": "1.3\nRegister applicant",
                        "w": 340,
                        "above": [("Citizen *", "entity", [(IN, "Registration details", NAVY)])],
                        "below": [
                            ("D1 *  Users", "store", [(OUT, "Pending user", TEAL)]),
                            ("D2  Profiles", "store", [(OUT, "Profile & address", TEAL)]),
                        ],
                    },
                    {
                        "title": "1.4\nSave staff account",
                        "above": [("Administrator", "entity_admin", [(IN, "Users & roles", PURPLE)])],
                        "below": [("D1 *  Users & roles", "store", [(OUT, "Account & permissions", TEAL)])],
                    },
                ],
            ),
        ],
        "1.1 must pass before 1.2 will accept the login. Google sign-in matches an existing verified email and does not create an account.\n"
        "1.3 creates the applicant user, profile and address. A public applicant stays inactive until staff accept or approve the application.\n"
        "1.4 is how an administrator adds a staff user and role. 1.5 opens the session and writes the login to the audit log.",
    )


def page_l2_publish() -> Page:
    return chain_page(
        "2.0 Publish",
        "  Level 2 of process 2.0  ·  Publish programs and announcements",
        "Each step stores one part of the public catalogue. Nothing is passed sideways, so each column stands on its own.",
        [
            (
                "",
                [
                    {
                        "title": "2.1\nSave the program",
                        "above": [("Administrator", "entity_admin", [(IN, "Program & rules", PURPLE)])],
                        "below": [("D3  Programs & rules", "store", [(OUT, "Catalogue & rules", TEAL)])],
                    },
                    {
                        "title": "2.2\nSave announcement",
                        "above": [("Administrator *", "entity_admin", [(IN, "Announcement text", PURPLE)])],
                        "below": [("D7  Announcements", "store", [(OUT, "Announcement", TEAL)])],
                    },
                    {
                        "title": "2.3\nSave settings",
                        "above": [("Administrator *", "entity_admin", [(IN, "Settings & workflow", PURPLE)])],
                        "below": [("D10  Settings", "store", [(OUT, "Agency & workflow staff", TEAL)])],
                    },
                    {
                        "title": "2.4\nShow the catalogue",
                        "w": 300,
                        "above": [("Citizen", "entity", [(OUT, "Programs & announcements", NAVY)])],
                        "below": [
                            ("D3 *  Programs", "store", [(IN, "Open programs", TEAL)]),
                            ("D7 *  Announcements", "store", [(IN, "Latest posts", TEAL)]),
                        ],
                    },
                ],
            )
        ],
        "A program stores its category, requirements, eligibility rules and form fields in D3.\n"
        "D10 stores the agency details and which staff member is assigned to verification, evaluation and approval.\n"
        "2.4 is what the public site reads: open programs, requirements, and announcements.",
    )


def page_l2_capture() -> Page:
    return chain_page(
        "3.0 Capture",
        "  Level 2 of process 3.0  ·  Capture and track applications",
        "The row is one application, from the open-program check through the confirmation. Data that is not stored yet moves to the next step.",
        [
            (
                "",
                [
                    {
                        "title": "3.1\nConfirm program\nis open",
                        "below": [("D3  Programs & rules", "store", [(IN, "Dates, slots & type", TEAL)])],
                        "next": "Open program",
                    },
                    {
                        "title": "3.2\nCheck Turnstile\nand email OTP",
                        "w": 300,
                        "above": [("Citizen", "entity", [(IN, "Email & OTP", NAVY)])],
                        "below": [
                            (
                                "Turnstile *",
                                "external",
                                [(OUT, "Token", PURPLE), (IN, "Pass / fail", PURPLE)],
                            ),
                            ("Email *", "external", [(OUT, "OTP mail", PURPLE)]),
                        ],
                        "next": "Verified email",
                    },
                    {
                        "title": "3.3\nSave applicant\nand user",
                        "w": 300,
                        "below": [
                            ("D1  Users", "store", [(OUT, "Pending user", TEAL)]),
                            ("D2  Profiles", "store", [(OUT, "Profile & address", TEAL)]),
                        ],
                        "next": "Applicant",
                    },
                    {
                        "title": "3.4\nSave application\nand answers",
                        "above": [("Citizen *", "entity", [(IN, "Identity & answers", NAVY)])],
                        "below": [("D4  Applications", "store", [(OUT, "Application & status", TEAL)])],
                        "next": "CAMS application",
                    },
                    {
                        "title": "3.5\nStore identity\nand documents",
                        "above": [("Citizen *", "entity", [(IN, "ID & files", NAVY)])],
                        "below": [("D5  Document files", "store", [(OUT, "File & requirement", TEAL)])],
                        "next": "Stored files",
                    },
                    {
                        "title": "3.6\nSend confirmation\nand status",
                        "w": 300,
                        "above": [
                            (
                                "Citizen *",
                                "entity",
                                [
                                    (IN, "Application number", NAVY),
                                    (OUT, "Status & timeline", NAVY),
                                ],
                            )
                        ],
                        "below": [
                            ("D8  Notifications", "store", [(OUT, "Notice", TEAL)]),
                            ("Email *", "external", [(OUT, "Received mail", PURPLE)]),
                        ],
                    },
                ],
            )
        ],
        "3.1 stops the application when the program is closed, the slot limit is full, or the beneficiary type does not match.\n"
        "An email that already belongs to an applicant updates that profile. A staff email is rejected.\n"
        "The new public user is inactive. The password emailed later is created only when staff accept the draft or give final approval.\n"
        "Files are PDF, JPG or PNG, up to 5 MB, saved under documents/{application}.",
    )


def page_l2_verify() -> Page:
    return chain_page(
        "4.0 Verify",
        "  Level 2 of process 4.0  ·  Verify documents",
        "The file is read, scored against the applicant profile, then accepted, rejected, or sent back.",
        [
            (
                "",
                [
                    {
                        "title": "4.1\nSend the file\nto OCR",
                        "w": 320,
                        "below": [
                            ("D5  Documents", "store", [(IN, "Stored file", TEAL)]),
                            (
                                "OCR.space *",
                                "external",
                                [(OUT, "Document image", PURPLE), (IN, "Parsed text", PURPLE)],
                            ),
                        ],
                        "next": "Raw text & type",
                    },
                    {
                        "title": "4.2\nScore fields\nagainst the profile",
                        "w": 320,
                        "below": [
                            ("D2  Profiles", "store", [(IN, "Profile values", TEAL)]),
                            ("D5 *  OCR fields", "store", [(OUT, "Score & type match", TEAL)]),
                        ],
                        "next": "Match summary",
                    },
                    {
                        "title": "4.3\nRecord the\ndocument decision",
                        "w": 460,
                        "above": [("LYDO Staff", "entity_staff", [(IN, "Verify, reject or revision", STAFF)])],
                        "below": [
                            ("D4  Applications", "store", [(OUT, "Verification status", TEAL)]),
                            ("Email *", "external", [(OUT, "Revision mail", PURPLE)]),
                            ("D8  Notifications", "store", [(OUT, "Document notice", TEAL)]),
                        ],
                    },
                ],
            )
        ],
        "4.2 warns the applicant when the scanned file does not look like the required document.\n"
        "4.3 writes the document verification and moves the application to under verification, incomplete, or for revision.\n"
        "A revision email asks the applicant to upload a replacement. The new file returns to 4.1.",
    )


def page_l2_evaluate() -> Page:
    return chain_page(
        "5.0 Evaluate",
        "  Level 2 of process 5.0  ·  Assess eligibility",
        "Rules are applied first. Staff then confirm any manual rule and the result is saved on the application.",
        [
            (
                "",
                [
                    {
                        "title": "5.1\nApply eligibility\nrules",
                        "w": 480,
                        "below": [
                            ("D3  Rules", "store", [(IN, "OCR & manual rules", TEAL)]),
                            ("D5  OCR fields", "store", [(IN, "Extracted fields", TEAL)]),
                            ("D4  Applications", "store", [(IN, "Form answers", TEAL)]),
                        ],
                        "next": "Rule results",
                    },
                    {
                        "title": "5.2\nSave the\nevaluation",
                        "above": [("LYDO Staff", "entity_staff", [(IN, "Manual check & remarks", STAFF)])],
                        "below": [("D4 *  Applications", "store", [(OUT, "Evaluation & next status", TEAL)])],
                    },
                ],
            )
        ],
        "Each program rule runs in OCR mode, against scanned text, or in manual mode, where staff confirm it.\n"
        "The saved evaluation recommends approve, send back for revision, or reject, and moves the application on.",
    )


def page_l2_approve() -> Page:
    return chain_page(
        "6.0 Approve",
        "  Level 2 of process 6.0  ·  Approve or return applications",
        "Staff record the decision. If the applicant is accepted, the account is switched on and the password is emailed.",
        [
            (
                "",
                [
                    {
                        "title": "6.1\nRecord the\ndecision",
                        "above": [
                            ("LYDO Staff", "entity_staff", [(IN, "Accept, approve, reject or revision", STAFF)])
                        ],
                        "below": [
                            (
                                "D4  Applications",
                                "store",
                                [
                                    (IN, "Application", TEAL),
                                    (OUT, "Approval & status", TEAL),
                                ],
                            )
                        ],
                        "next": "Decision",
                    },
                    {
                        "title": "6.2\nActivate login\nand send password",
                        "w": 460,
                        "below": [
                            ("D1  Users", "store", [(OUT, "Active account & password", TEAL)]),
                            ("Email *", "external", [(OUT, "Accepted or approved mail", PURPLE)]),
                            ("D8  Notifications", "store", [(OUT, "Decision notice", TEAL)]),
                        ],
                    },
                ],
            )
        ],
        "Accepting a draft sends ApplicationAcceptedMail. Final approval sends ApplicationApprovedMail. Both carry a temporary password.\n"
        "A rejection or a request for revision updates D4 and notifies the applicant. No password is sent in those cases.",
    )


def page_l2_release() -> Page:
    return chain_page(
        "7.0 Release",
        "  Level 2 of process 7.0  ·  Release assistance",
        "Schedule the release, record what was paid, then check the beneficiary's claim.",
        [
            (
                "",
                [
                    {
                        "title": "7.1\nSchedule\nthe release",
                        "w": 520,
                        "above": [("LYDO Staff", "entity_staff", [(IN, "Date, place & method", STAFF)])],
                        "below": [
                            ("D4  Applications", "store", [(IN, "Approved application", TEAL)]),
                            ("D6  Releases", "store", [(OUT, "Release schedule", TEAL)]),
                            ("Email *", "external", [(OUT, "Schedule mail", PURPLE)]),
                            ("Semaphore *", "external", [(OUT, "Schedule SMS", PURPLE)]),
                        ],
                        "next": "Schedule",
                    },
                    {
                        "title": "7.2\nRecord\nthe payout",
                        "w": 320,
                        "below": [
                            ("D6 *  Releases", "store", [(OUT, "Payout & REL number", TEAL)]),
                            ("D4 *  Applications", "store", [(OUT, "Released status", TEAL)]),
                        ],
                        "next": "Payout record",
                    },
                    {
                        "title": "7.3\nVerify\nthe claim",
                        "w": 340,
                        "above": [("LYDO Staff *", "entity_staff", [(IN, "Reference, QR or CAMS no.", STAFF)])],
                        "below": [
                            (
                                "D6 *  Releases",
                                "store",
                                [(IN, "Scheduled release", TEAL), (OUT, "Claim check", TEAL)],
                            ),
                            ("D4 *  Applications", "store", [(OUT, "Completed status", TEAL)]),
                        ],
                    },
                ],
            )
        ],
        "7.1 emails and texts the beneficiary. Methods are cash, bank, e-wallet, check, and other.\n"
        "7.2 stores the REL number, amount and method, and sets the application to released.\n"
        "7.3 records who checked the claim and sets the application to completed.",
    )


def page_l2_reports() -> Page:
    return chain_page(
        "8.0 Reports",
        "  Level 2 of process 8.0  ·  Produce reports and the audit trail",
        "The three reports are separate. Each one reads the store it needs and sends the result to the person who asked.",
        [
            (
                "",
                [
                    {
                        "title": "8.1\nApplication and\nfinancial report",
                        "w": 360,
                        "above": [
                            (
                                "Administrator",
                                "entity_admin",
                                [(IN, "Filters", PURPLE), (OUT, "Excel or PDF", PURPLE)],
                            )
                        ],
                        "below": [
                            ("D4  Applications", "store", [(IN, "Applications", TEAL)]),
                            ("D6  Releases", "store", [(IN, "Releases", TEAL)]),
                        ],
                    },
                    {
                        "title": "8.2\nBeneficiary\nreport",
                        "above": [("LYDO Staff", "entity_staff", [(OUT, "On-screen list & CSV", STAFF)])],
                        "below": [("D2  Profiles", "store", [(IN, "Beneficiaries", TEAL)])],
                    },
                    {
                        "title": "8.3\nShow the\naudit log",
                        "above": [("Administrator *", "entity_admin", [(OUT, "Audit list", PURPLE)])],
                        "below": [("D9  Audit log", "store", [(IN, "Audit events", TEAL)])],
                    },
                ],
            )
        ],
        "8.1 covers the application report and the financial report. Both can be exported to Excel or PDF.\n"
        "8.2 groups beneficiaries by barangay and can be downloaded as CSV.\n"
        "8.3 is the audit log written when someone signs in or changes an application, release, or account.",
    )


def write_file(path: Path, pages: list[tuple[str, Page]]) -> None:
    for _, page in pages:
        hits = page.overlaps() + page.line_problems()
        if hits:
            raise SystemExit(f"{path.name} / {page.name}:\n  " + "\n  ".join(hits))
    inner = "\n".join(page.xml(diagram_id) for diagram_id, page in pages)
    xml = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<mxfile host="app.diagrams.net" agent="CAMS DFD generator" version="22.1.0" type="device">\n'
        f"{inner}\n"
        "</mxfile>\n"
    )
    path.write_text(xml, encoding="utf-8")
    print(f"Wrote {path} ({len(xml):,} bytes, {len(pages)} page{'s' if len(pages) != 1 else ''})")


def main() -> None:
    ROOT.mkdir(parents=True, exist_ok=True)
    write_file(ROOT / "LYDO-CAMS-DFD-Level-0.drawio", [("context", page_context())])
    write_file(
        ROOT / "LYDO-CAMS-DFD-Level-1.drawio",
        [("intake", page_level1_intake()), ("office", page_level1_office())],
    )
    write_file(
        ROOT / "LYDO-CAMS-DFD-Level-2.drawio",
        [
            ("auth", page_l2_auth()),
            ("publish", page_l2_publish()),
            ("capture", page_l2_capture()),
            ("verify", page_l2_verify()),
            ("evaluate", page_l2_evaluate()),
            ("approve", page_l2_approve()),
            ("release", page_l2_release()),
            ("reports", page_l2_reports()),
        ],
    )


if __name__ == "__main__":
    main()
