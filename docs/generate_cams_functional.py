"""Generate the LYDO CAMS functional decomposition diagram as a draw.io file.

The eight functions are the Level 1 processes. The boxes under each one are
the Level 2 steps. Lines show composition only, and each line stays on its
own path so none cross or pass through a box.
"""

from __future__ import annotations

from pathlib import Path
from xml.sax.saxutils import escape as xml_escape

OUT = Path(r"C:\Users\John Lloyd\Desktop\NEA\docs\LYDO-CAMS-Functional-Diagram.drawio")

NAVY = "#1B365D"
BLUE = "#1B4F72"
GOLD = "#B45309"
PURPLE = "#6D28D9"

# (number, title, stroke, fill, children[(number, title)])
FUNCTIONS = [
    (
        "1.0",
        "Authenticate and\nregister accounts",
        BLUE,
        "#E8F1F8",
        [
            ("1.1", "Check Turnstile"),
            ("1.2", "Sign in"),
            ("1.3", "Register applicant"),
            ("1.4", "Save staff account"),
            ("1.5", "Start session"),
        ],
    ),
    (
        "2.0",
        "Publish programs\nand announcements",
        BLUE,
        "#E8F1F8",
        [
            ("2.1", "Save the program"),
            ("2.2", "Save announcement"),
            ("2.3", "Save settings"),
            ("2.4", "Show the catalogue"),
        ],
    ),
    (
        "3.0",
        "Capture and track\napplications",
        BLUE,
        "#E8F1F8",
        [
            ("3.1", "Confirm the program is open"),
            ("3.2", "Check Turnstile and email OTP"),
            ("3.3", "Save the applicant and user"),
            ("3.4", "Save the application and answers"),
            ("3.5", "Store identity and documents"),
            ("3.6", "Send confirmation and status"),
        ],
    ),
    (
        "4.0",
        "Verify\ndocuments",
        GOLD,
        "#FFFBEB",
        [
            ("4.1", "Send the file to OCR"),
            ("4.2", "Score fields against the profile"),
            ("4.3", "Record the document decision"),
        ],
    ),
    (
        "5.0",
        "Assess\neligibility",
        GOLD,
        "#FFFBEB",
        [
            ("5.1", "Apply eligibility rules"),
            ("5.2", "Save the evaluation"),
        ],
    ),
    (
        "6.0",
        "Approve or return\napplications",
        GOLD,
        "#FFFBEB",
        [
            ("6.1", "Record the decision"),
            ("6.2", "Activate login and send password"),
        ],
    ),
    (
        "7.0",
        "Release\nassistance",
        GOLD,
        "#FFFBEB",
        [
            ("7.1", "Schedule the release"),
            ("7.2", "Record the payout"),
            ("7.3", "Verify the claim"),
        ],
    ),
    (
        "8.0",
        "Produce reports\nand the audit trail",
        PURPLE,
        "#F5F3FF",
        [
            ("8.1", "Application and financial report"),
            ("8.2", "Beneficiary report"),
            ("8.3", "Show the audit log"),
        ],
    ),
]


def val(text: str) -> str:
    text = xml_escape(str(text), {'"': "&quot;"})
    return text.replace("\n", "&lt;br&gt;")


class Diagram:
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

    def v(self, text: str, x: float, y: float, w: float, h: float, style: str) -> str:
        i = self._id()
        self.nodes.append(
            f'<mxCell id="{i}" value="{val(text)}" style="{style}" vertex="1" parent="1">'
            f'<mxGeometry x="{x:.0f}" y="{y:.0f}" width="{w:.0f}" height="{h:.0f}" as="geometry"/></mxCell>'
        )
        return i

    def link(
        self,
        source: str,
        target: str,
        exit_xy: tuple[float, float],
        entry_xy: tuple[float, float],
        points: list[tuple[float, float]],
        color: str,
    ) -> None:
        i = self._id()
        pts = "".join(f'<mxPoint x="{x:.0f}" y="{y:.0f}"/>' for x, y in points)
        array = f"<Array as=\"points\">{pts}</Array>" if pts else ""
        style = (
            "rounded=0;html=1;endArrow=none;startArrow=none;strokeWidth=1.7;"
            f"strokeColor={color};exitX={exit_xy[0]};exitY={exit_xy[1]};"
            f"entryX={entry_xy[0]};entryY={entry_xy[1]};exitDx=0;exitDy=0;entryDx=0;entryDy=0;"
        )
        self.edges.append(
            f'<mxCell id="{i}" value="" style="{style}" edge="1" parent="1" '
            f'source="{source}" target="{target}">'
            f'<mxGeometry relative="1" as="geometry">{array}</mxGeometry></mxCell>'
        )

    def xml(self) -> str:
        body = "\n        ".join(self.nodes + self.edges)
        return f"""<?xml version="1.0" encoding="UTF-8"?>
<mxfile host="app.diagrams.net" agent="CAMS functional diagram" version="22.1.0" type="device">
  <diagram id="functional" name="{val(self.name)}">
    <mxGraphModel dx="1400" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="{self.width}" pageHeight="{self.height}" math="0" shadow="0">
      <root>
        <mxCell id="0"/>
        <mxCell id="1" parent="0"/>
        {body}
      </root>
    </mxGraphModel>
  </diagram>
</mxfile>
"""


def box_style(fill: str, stroke: str, size: int, bold: bool = True) -> str:
    weight = "fontStyle=1;" if bold else ""
    return (
        "rounded=1;whiteSpace=wrap;html=1;arcSize=8;spacingLeft=8;spacingRight=8;"
        f"fillColor={fill};strokeColor={stroke};strokeWidth=2;"
        f"fontSize={size};{weight}fontColor=#0F172A;align=center;verticalAlign=middle;"
    )


def build() -> Diagram:
    margin = 40
    col_w = 292
    col_gap = 22
    parent_h = 78
    child_h = 52
    child_gap = 14
    columns = len(FUNCTIONS)
    width = margin * 2 + columns * col_w + (columns - 1) * col_gap

    root_w, root_h = 520, 78
    root_y = 108
    bus_y = root_y + root_h + 34
    parent_y = bus_y + 28
    first_child_y = parent_y + parent_h + 24
    tallest = max(len(children) for *_, children in FUNCTIONS)
    children_bottom = first_child_y + tallest * child_h + (tallest - 1) * child_gap
    legend_h = 78
    height = int(children_bottom + 36 + legend_h + 28)

    page = Diagram("Functional decomposition", width, height)
    page.v(
        "  LYDO Nabua  ·  CAMS functional decomposition",
        0,
        0,
        width,
        52,
        "rounded=0;whiteSpace=wrap;html=1;fillColor=#1B365D;strokeColor=none;"
        "fontColor=#FFFFFF;fontStyle=1;fontSize=18;align=left;spacingLeft=18;",
    )
    page.v(
        "Community Assistance Management System  ·  Municipality of Nabua, Camarines Sur. "
        "Each box under a function is part of that function.",
        margin,
        58,
        width - margin * 2,
        34,
        "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;fontSize=12;fontColor=#475569;",
    )

    root_x = (width - root_w) / 2
    root = page.v(
        "LYDO CAMS\nCommunity Assistance Management System",
        root_x,
        root_y,
        root_w,
        root_h,
        "rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#1B365D;fontColor=#FFFFFF;"
        "strokeColor=#0F2444;strokeWidth=2;fontSize=18;fontStyle=1;shadow=1;verticalAlign=middle;",
    )
    root_cx = root_x + root_w / 2

    for index, (number, title, stroke, fill, children) in enumerate(FUNCTIONS):
        col_x = margin + index * (col_w + col_gap)
        parent_cx = col_x + col_w / 2
        parent = page.v(
            f"{number}\n{title}",
            col_x,
            parent_y,
            col_w,
            parent_h,
            box_style(fill, stroke, 13),
        )
        page.link(root, parent, (0.5, 1), (0.5, 0), [(root_cx, bus_y), (parent_cx, bus_y)], NAVY)

        rail_x = col_x + 16
        child_x = col_x + 36
        child_w = col_w - 36
        exit_x = (rail_x - col_x) / col_w
        for child_index, (child_number, child_title) in enumerate(children):
            child_y = first_child_y + child_index * (child_h + child_gap)
            child = page.v(
                f"{child_number}   {child_title}",
                child_x,
                child_y,
                child_w,
                child_h,
                box_style("#FFFFFF", stroke, 12, bold=False),
            )
            child_mid = child_y + child_h / 2
            page.link(
                parent,
                child,
                (exit_x, 1),
                (0, 0.5),
                [(rail_x, child_mid)],
                stroke,
            )

    page.v(
        "The eight functions are the Level 1 processes. The boxes under each one are its Level 2 steps.\n"
        "A line means the lower function is part of the upper function. It does not show data moving.\n"
        "Blue is accounts, the catalogue, and applications. Gold is verification through release. Purple is reports.\n"
        "The same numbers are used on the data-flow diagrams in the dfd folder.",
        margin,
        children_bottom + 36,
        width - margin * 2,
        legend_h,
        "rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#FFFFFF;strokeColor=#CBD5E1;"
        "align=left;spacingLeft=12;spacingTop=8;verticalAlign=top;fontSize=12;fontColor=#0F172A;",
    )
    return page


def main() -> None:
    try:
        from PIL import ImageFont

        regular = ImageFont.truetype(r"C:\Windows\Fonts\arial.ttf", 12)
        bold = ImageFont.truetype(r"C:\Windows\Fonts\arialbd.ttf", 13)
    except Exception:
        regular = bold = None
    if regular is not None:
        child_inner = 292 - 36 - 20
        for number, title, _stroke, _fill, children in FUNCTIONS:
            for line in title.split("\n"):
                if bold.getlength(line) > 292 - 20:
                    raise SystemExit(f"Parent title does not fit: {number} {line}")
            for child_number, child_title in children:
                label = f"{child_number}   {child_title}"
                if regular.getlength(label) > child_inner:
                    raise SystemExit(f"Child title does not fit: {label}")
    page = build()
    OUT.write_text(page.xml(), encoding="utf-8")
    print(f"Wrote {OUT} ({OUT.stat().st_size:,} bytes)")


if __name__ == "__main__":
    main()
