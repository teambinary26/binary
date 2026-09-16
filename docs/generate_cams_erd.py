"""Generate the LYDO CAMS entity-relationship diagram as a draw.io file."""

from __future__ import annotations

from xml.sax.saxutils import escape as xml_escape

OUT = r"C:\Users\John Lloyd\Desktop\NEA\docs\LYDO-CAMS-ERD.drawio"

HEADER = (
    "swimlane;fontStyle=1;childLayout=stackLayout;horizontal=1;startSize=30;"
    "horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=0;"
    "marginBottom=0;html=1;whiteSpace=wrap;fontColor=#FFFFFF;strokeColor=#0F172A;"
    "strokeWidth=1.5;fontSize=13;align=center;"
)

ROW = (
    "text;strokeColor=none;align=left;verticalAlign=middle;spacingLeft=8;spacingRight=4;"
    "overflow=hidden;rotatable=0;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;"
    "fontSize=11;fontColor=#0F172A;whiteSpace=wrap;html=1;"
)

REL = (
    "edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;html=1;"
    "strokeWidth=1.8;fontSize=10;"
    "fontStyle=1;labelBackgroundColor=#FFFFFF;endFill=0;startFill=0;"
    "jumpStyle=arc;jumpSize=8;"
)

PAD = 14
ENT_W = 260
COL_GAP = 140

COLORS = {
    "auth": "#1B365D",
    "applicant": "#0F766E",
    "program": "#1D4ED8",
    "application": "#B45309",
    "document": "#6D28D9",
    "release": "#166534",
    "comms": "#BE185D",
    "system": "#475569",
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
        self.pos: dict[str, tuple[float, float, float, float]] = {}
        self.bus_n = 0
        self.top_bus = 136

    def _id(self) -> str:
        i = str(self._n)
        self._n += 1
        return i

    def box(self, text: str, x: float, y: float, w: float, h: float, style: str) -> str:
        i = self._id()
        self.nodes.append(
            f'<mxCell id="{i}" value="{val(text)}" style="{style}" vertex="1" parent="1">'
            f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/></mxCell>'
        )
        return i

    def entity(self, title: str, attrs: list[tuple[str, str, str]], x: float, y: float, color: str, w: float = ENT_W) -> str:
        row_h = 20
        header_h = 30
        height = header_h + row_h * len(attrs)
        eid = self._id()
        self.pos[eid] = (x, y, w, height)
        self.nodes.append(
            f'<mxCell id="{eid}" value="{val(title)}" style="{HEADER}fillColor={color};" vertex="1" parent="1">'
            f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{height}" as="geometry"/></mxCell>'
        )
        for i, (kind, name, typ) in enumerate(attrs):
            rid = self._id()
            mark = {"PK": "PK", "FK": "FK", "UK": "UK", "PF": "PK FK"}.get(kind, "")
            label = f"{mark}  {name}   {typ}".strip() if mark else f"     {name}   {typ}"
            if kind == "PK":
                fill, extra = "#FEF3C7", "fontStyle=4;"
            elif kind in {"FK", "PF"}:
                fill, extra = "#DBEAFE", "fontStyle=0;"
            elif kind == "UK":
                fill, extra = "#D1FAE5", "fontStyle=0;"
            else:
                fill, extra = ("#FFFFFF" if i % 2 == 0 else "#F8FAFC"), ""
            self.nodes.append(
                f'<mxCell id="{rid}" value="{val(label)}" style="{ROW}fillColor={fill};{extra}" vertex="1" parent="{eid}">'
                f'<mxGeometry y="{header_h + i * row_h}" width="{w}" height="{row_h}" as="geometry"/></mxCell>'
            )
        return eid

    def _obstacles(self, *skip: str) -> list[tuple[float, float, float, float]]:
        return [b for i, b in self.pos.items() if i not in skip]

    def _seg_hits(self, x1: float, y1: float, x2: float, y2: float, boxes) -> bool:
        if abs(x1 - x2) < 1:
            y_lo, y_hi = min(y1, y2), max(y1, y2)
            for bx, by, bw, bh in boxes:
                if bx - PAD <= x1 <= bx + bw + PAD and not (y_hi < by - PAD or y_lo > by + bh + PAD):
                    return True
            return False
        x_lo, x_hi = min(x1, x2), max(x1, x2)
        for bx, by, bw, bh in boxes:
            if by - PAD <= y1 <= by + bh + PAD and not (x_hi < bx - PAD or x_lo > bx + bw + PAD):
                return True
        return False

    def _col_xs(self) -> list[float]:
        return sorted({pos[0] for pos in self.pos.values()})

    def _gutter(self, x: float, w: float, side: str) -> float:
        return x + w + COL_GAP / 2 if side == "e" else x - COL_GAP / 2

    def _bottom_bus(self) -> float:
        return max(y + h for _, y, _, h in self.pos.values()) + 28

    def _take_bus_y(self, use_bottom: bool) -> float:
        lane = self.bus_n % 10
        self.bus_n += 1
        base = self._bottom_bus() if use_bottom else self.top_bus
        return base + lane * 7

    def _route_points(self, parent: str, child: str, exit_: str, entry_: str) -> list[tuple[int, int]]:
        px, py, pw, ph = self.pos[parent]
        cx, cy, cw, ch = self.pos[child]
        boxes = self._obstacles(parent, child)
        sx = px + pw if exit_ == "e" else px if exit_ == "w" else px + pw / 2
        sy = py + ph if exit_ == "s" else py if exit_ == "n" else py + ph / 2
        tx = cx + cw if entry_ == "e" else cx if entry_ == "w" else cx + cw / 2
        ty = cy + ch if entry_ == "s" else cy if entry_ == "n" else cy + ch / 2

        same_col = abs(px - cx) < 8
        if same_col and exit_ in "ns" and entry_ in "ns":
            if not self._seg_hits(sx, sy, sx, ty, boxes):
                return []
            gx = self._gutter(px, pw, "e")
            return [(int(gx), int(sy)), (int(gx), int(ty))]

        gx1 = self._gutter(px, pw, exit_ if exit_ in "ew" else ("e" if px <= cx else "w"))
        gx2 = self._gutter(cx, cw, entry_ if entry_ in "ew" else ("w" if px <= cx else "e"))

        if exit_ in "ew" and entry_ in "ew":
            cols = self._col_xs()
            pi = min(range(len(cols)), key=lambda i: abs(cols[i] - px))
            ci = min(range(len(cols)), key=lambda i: abs(cols[i] - cx))
            adjacent = abs(pi - ci) <= 1
            if adjacent and not self._seg_hits(gx1, sy, gx2, sy, boxes) and not self._seg_hits(gx2, sy, gx2, ty, boxes):
                return [(int(gx1), int(sy)), (int(gx2), int(sy)), (int(gx2), int(ty))]
            if adjacent and not self._seg_hits(gx1, ty, gx2, ty, boxes) and not self._seg_hits(gx1, sy, gx1, ty, boxes):
                return [(int(gx1), int(sy)), (int(gx1), int(ty)), (int(gx2), int(ty))]
            hy = self._take_bus_y(use_bottom=self.bus_n % 2 == 1)
            return [(int(gx1), int(sy)), (int(gx1), int(hy)), (int(gx2), int(hy)), (int(gx2), int(ty))]

        gx = self._gutter(px, pw, "e")
        return [(int(gx), int(sy)), (int(gx), int(ty))]

    def rel(
        self,
        parent: str,
        child: str,
        label: str,
        kind: str = "1n",
        optional: bool = False,
        color: str = "#334155",
    ) -> None:
        arrows = {
            "1n": ("ERzeroToOne" if optional else "ERmandOne", "ERzeroToMany"),
            "1n+": ("ERmandOne", "ERoneToMany"),
            "11": ("ERmandOne", "ERzeroToOne" if optional else "ERmandOne"),
            "n1": ("ERzeroToMany", "ERmandOne"),
        }
        start, end = arrows[kind]
        px, py, pw, ph = self.pos[parent]
        cx, cy, cw, ch = self.pos[child]
        if abs(px - cx) < 8:
            exit_, entry = ("s", "n") if py <= cy else ("n", "s")
        elif px < cx:
            exit_, entry = "e", "w"
        else:
            exit_, entry = "w", "e"

        cleaned: list[tuple[int, int]] = []
        for pt in self._route_points(parent, child, exit_, entry):
            if not cleaned or cleaned[-1] != pt:
                cleaned.append(pt)

        sx = px + pw if exit_ == "e" else px if exit_ == "w" else px + pw / 2
        sy = py + ph if exit_ == "s" else py if exit_ == "n" else py + ph / 2
        tx = cx + cw if entry == "e" else cx if entry == "w" else cx + cw / 2
        ty = cy + ch if entry == "s" else cy if entry == "n" else cy + ch / 2
        boxes = self._obstacles(parent, child)
        path = [(sx, sy), *cleaned, (tx, ty)]
        for a, b in zip(path, path[1:]):
            if self._seg_hits(a[0], a[1], b[0], b[1], boxes):
                print(f"WARN route hits box: {label} ({parent}->{child}) segment {a}->{b}")
                break

        i = self._id()
        style = (
            f"{REL}strokeColor={color};fontColor={color};"
            f"startArrow={start};endArrow={end};"
            f"exitX={'1' if exit_ == 'e' else '0' if exit_ == 'w' else '0.5'};"
            f"exitY={'1' if exit_ == 's' else '0' if exit_ == 'n' else '0.5'};"
            f"entryX={'1' if entry == 'e' else '0' if entry == 'w' else '0.5'};"
            f"entryY={'1' if entry == 's' else '0' if entry == 'n' else '0.5'};"
            "exitDx=0;exitDy=0;entryDx=0;entryDy=0;"
        )
        pts_xml = ""
        if cleaned:
            pts_xml = "<Array as=\"points\">" + "".join(
                f'<mxPoint x="{x}" y="{y}"/>' for x, y in cleaned
            ) + "</Array>"
        geom = f'<mxGeometry relative="1" as="geometry">{pts_xml}<mxPoint as="offset" x="0" y="-10"/></mxGeometry>'
        self.edges.append(
            f'<mxCell id="{i}" value="{val(label)}" style="{style}" edge="1" parent="1" '
            f'source="{parent}" target="{child}">{geom}</mxCell>'
        )

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


BANNER = "rounded=0;whiteSpace=wrap;html=1;fillColor=#1B365D;strokeColor=none;fontColor=#FFFFFF;fontStyle=1;fontSize=20;align=left;spacingLeft=20;"
SUB = "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;fontSize=12;fontColor=#475569;"
NOTE = "rounded=1;whiteSpace=wrap;html=1;align=left;verticalAlign=top;spacingLeft=10;spacingTop=8;fillColor=#FFFFFF;strokeColor=#CBD5E1;fontSize=11;fontColor=#0F172A;"
LEGEND_BOX = "rounded=1;whiteSpace=wrap;html=1;align=left;verticalAlign=top;spacingLeft=10;spacingTop=8;fillColor=#F8FAFC;strokeColor=#94A3B8;fontSize=11;fontColor=#0F172A;"


OVERVIEW = {
    "roles": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("", "name", "string"),
        ("UK", "slug", "string"),
        ("", "description", "string NULL"),
    ]),
    "permissions": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("", "name", "string"),
        ("UK", "slug", "string"),
        ("", "module", "string"),
    ]),
    "role_permissions": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("FK", "role_id", "bigint"),
        ("FK", "permission_id", "bigint"),
        ("UK", "(role_id, permission_id)", ""),
    ]),
    "users": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("FK", "role_id", "bigint NULL"),
        ("", "name", "string"),
        ("UK", "email", "string"),
        ("", "password", "string"),
        ("", "employee_no", "string NULL"),
        ("", "is_active", "boolean"),
        ("", "pending_account", "boolean"),
        ("", "last_login_at", "timestamp NULL"),
    ]),
    "workflow_staff": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("", "workflow_step", "string(40)"),
        ("FK", "user_id", "bigint"),
        ("UK", "(workflow_step, user_id)", ""),
    ]),
    "applicants": (COLORS["applicant"], [
        ("PK", "id", "bigint"),
        ("UK", "user_id", "bigint FK"),
        ("UK", "applicant_no", "string  BEN-"),
        ("", "full_name", "string"),
        ("", "date_of_birth", "date"),
        ("", "sex", "string(20)"),
        ("", "contact_number", "string(30)"),
        ("", "email", "string"),
    ]),
    "applicant_profiles": (COLORS["applicant"], [
        ("PK", "id", "bigint"),
        ("UK", "applicant_id", "bigint FK"),
        ("", "beneficiary_type", "string(30)"),
        ("", "school_name", "string NULL"),
        ("", "course_or_program", "string NULL"),
        ("", "year_level", "string NULL"),
        ("", "is_pwd", "boolean"),
        ("", "pwd_type", "string(50) NULL"),
    ]),
    "applicant_addresses": (COLORS["applicant"], [
        ("PK", "id", "bigint"),
        ("FK", "applicant_id", "bigint"),
        ("", "street", "string"),
        ("", "barangay", "string"),
        ("", "municipality", "string"),
        ("", "province", "string"),
        ("", "is_primary", "boolean"),
    ]),
    "program_categories": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("", "name", "string"),
        ("UK", "slug", "string"),
        ("", "group", "string(30)"),
        ("", "is_active", "boolean"),
        ("", "sort_order", "uint"),
    ]),
    "assistance_programs": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("FK", "program_category_id", "bigint"),
        ("", "name", "string"),
        ("UK", "code", "string"),
        ("UK", "slug", "string"),
        ("", "beneficiary_type", "string(30)"),
        ("", "amount / amount_max", "decimal(12,2)"),
        ("", "is_open, open_from, open_until", ""),
        ("", "slot_limit", "uint NULL"),
    ]),
    "program_requirements": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("FK", "assistance_program_id", "bigint"),
        ("", "name", "string"),
        ("", "is_required", "boolean"),
        ("", "ocr_fields", "json NULL"),
    ]),
    "program_eligibility_rules": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("FK", "assistance_program_id", "bigint"),
        ("", "label", "string"),
        ("", "field / operator / value", "string NULL"),
        ("", "check_mode", "ocr | manual"),
    ]),
    "program_form_fields": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("FK", "assistance_program_id", "bigint"),
        ("", "name / label", "string"),
        ("", "type", "string(30)"),
        ("", "options", "json NULL"),
        ("", "is_required", "boolean"),
    ]),
    "applications": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("UK", "application_no", "string  CAMS-"),
        ("FK", "applicant_id", "bigint"),
        ("FK", "assistance_program_id", "bigint"),
        ("", "status", "string(40)"),
        ("", "current_step", "tinyint"),
        ("", "submitted_at", "timestamp NULL"),
        ("FK", "assigned_staff_id", "bigint NULL"),
        ("", "approved_amount", "decimal(12,2) NULL"),
    ]),
    "application_answers": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint"),
        ("FK", "program_form_field_id", "bigint NULL"),
        ("", "field_name / field_label", "string"),
        ("", "value", "text NULL"),
    ]),
    "application_status_history": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint"),
        ("", "from_status", "string(40) NULL"),
        ("", "to_status", "string(40)"),
        ("FK", "user_id", "bigint NULL"),
        ("", "remarks", "text NULL"),
        ("", "created_at", "timestamp"),
    ]),
    "application_evaluations": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint"),
        ("FK", "evaluator_id", "bigint"),
        ("", "eligibility_passed", "boolean"),
        ("", "eligibility_checks", "json NULL"),
        ("", "recommendation", "string(30)"),
        ("", "recommended_amount", "decimal NULL"),
    ]),
    "application_approvals": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint"),
        ("FK", "officer_id", "bigint"),
        ("", "decision", "string(30)"),
        ("", "approved_amount", "decimal NULL"),
        ("", "decided_at", "timestamp NULL"),
    ]),
    "document_submissions": (COLORS["document"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint"),
        ("FK", "program_requirement_id", "bigint NULL"),
        ("", "requirement_name", "string"),
        ("", "file_path / original_name", "string"),
        ("", "mime_type, file_size, uploaded_at", ""),
    ]),
    "document_verifications": (COLORS["document"], [
        ("PK", "id", "bigint"),
        ("UK", "document_submission_id", "bigint FK"),
        ("", "status", "string(40)"),
        ("FK", "verified_by", "bigint NULL"),
        ("", "verified_at", "timestamp NULL"),
        ("", "remarks", "text NULL"),
    ]),
    "ocr_results": (COLORS["document"], [
        ("PK", "id", "bigint"),
        ("UK", "document_submission_id", "bigint FK"),
        ("", "status", "string(30)"),
        ("", "expected_type / detected_type", "string NULL"),
        ("", "overall_score / overall_status", ""),
        ("", "raw_text", "longtext NULL"),
    ]),
    "ocr_extracted_fields": (COLORS["document"], [
        ("PK", "id", "bigint"),
        ("FK", "ocr_result_id", "bigint"),
        ("", "field_key / field_label", "string"),
        ("", "expected / extracted / corrected", "text"),
        ("", "match_status / match_score", ""),
    ]),
    "release_schedules": (COLORS["release"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint"),
        ("", "release_date", "date"),
        ("", "release_location", "string"),
        ("", "release_method", "string(40)"),
        ("", "status", "string(30)"),
        ("FK", "scheduled_by", "bigint"),
    ]),
    "assistance_releases": (COLORS["release"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint"),
        ("FK", "release_schedule_id", "bigint NULL"),
        ("", "amount", "decimal(12,2)"),
        ("FK", "released_by", "bigint"),
        ("UK", "reference_no", "string  REL-"),
        ("UK", "verification_code", "string  QR"),
    ]),
    "release_verifications": (COLORS["release"], [
        ("PK", "id", "bigint"),
        ("FK", "assistance_release_id", "bigint"),
        ("FK", "verified_by", "bigint"),
        ("", "result", "string(40)"),
        ("", "lookup_method", "string(40) NULL"),
        ("", "verified_at", "timestamp NULL"),
    ]),
    "announcements": (COLORS["comms"], [
        ("PK", "id", "bigint"),
        ("", "title / type", "string"),
        ("", "body", "text"),
        ("", "is_published", "boolean"),
        ("FK", "author_id", "bigint"),
        ("", "published_at", "timestamp NULL"),
    ]),
    "system_notifications": (COLORS["comms"], [
        ("PK", "id", "bigint"),
        ("FK", "user_id", "bigint"),
        ("", "title / body / type", ""),
        ("FK", "application_id", "bigint NULL"),
        ("", "read_at", "timestamp NULL"),
    ]),
    "audit_logs": (COLORS["system"], [
        ("PK", "id", "bigint"),
        ("FK", "user_id", "bigint NULL"),
        ("", "action", "string(40)"),
        ("FK", "application_id", "bigint NULL"),
        ("", "subject_type / subject_id", "morph NULL"),
        ("", "ip_address / description", ""),
    ]),
    "system_settings": (COLORS["system"], [
        ("PK", "id", "bigint"),
        ("UK", "key", "string"),
        ("", "value", "text NULL"),
        ("", "group", "string"),
    ]),
}

PHYSICAL = {
    "roles": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("", "name", "string"),
        ("UK", "slug", "string"),
        ("", "description", "string NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "permissions": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("", "name", "string"),
        ("UK", "slug", "string"),
        ("", "module", "string"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "role_permissions": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("FK", "role_id", "bigint  cascade"),
        ("FK", "permission_id", "bigint  cascade"),
        ("UK", "unique(role_id, permission_id)", ""),
    ]),
    "users": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("FK", "role_id", "bigint NULL  set null"),
        ("", "name", "string"),
        ("", "employee_no", "string NULL"),
        ("", "office", "string NULL"),
        ("UK", "email", "string"),
        ("", "email_verified_at", "timestamp NULL"),
        ("", "password", "string"),
        ("", "remember_token", "string NULL"),
        ("", "is_active", "boolean default true"),
        ("", "pending_account", "boolean default false"),
        ("", "last_login_at", "timestamp NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "workflow_staff": (COLORS["auth"], [
        ("PK", "id", "bigint"),
        ("", "workflow_step", "string(40)"),
        ("FK", "user_id", "bigint  cascade"),
        ("UK", "unique(workflow_step, user_id)", ""),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "applicants": (COLORS["applicant"], [
        ("PK", "id", "bigint"),
        ("UK", "user_id", "bigint FK  cascade"),
        ("UK", "applicant_no", "string"),
        ("", "full_name", "string  indexed"),
        ("", "date_of_birth", "date"),
        ("", "sex", "string(20)"),
        ("", "contact_number", "string(30)"),
        ("", "email", "string  indexed"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "applicant_profiles": (COLORS["applicant"], [
        ("PK", "id", "bigint"),
        ("UK", "applicant_id", "bigint FK  cascade"),
        ("", "beneficiary_type", "string(30)"),
        ("", "school_name", "string NULL"),
        ("", "course_or_program", "string NULL"),
        ("", "year_level", "string NULL"),
        ("", "mother_name / occupation", "string NULL"),
        ("", "father_name / occupation", "string NULL"),
        ("", "is_pwd", "boolean"),
        ("", "pwd_type", "string(50) NULL"),
        ("", "pwd_type_detail", "string NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "applicant_addresses": (COLORS["applicant"], [
        ("PK", "id", "bigint"),
        ("FK", "applicant_id", "bigint  cascade"),
        ("", "street", "string"),
        ("", "barangay", "string  indexed"),
        ("", "municipality", "string  indexed"),
        ("", "province", "string"),
        ("", "is_primary", "boolean"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "program_categories": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("", "name", "string"),
        ("UK", "slug", "string"),
        ("", "group", "string(30)"),
        ("", "description", "text NULL"),
        ("", "sort_order", "uint"),
        ("", "is_active", "boolean"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "assistance_programs": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("FK", "program_category_id", "bigint  restrict"),
        ("", "name", "string"),
        ("UK", "code", "string"),
        ("UK", "slug", "string"),
        ("", "description", "text"),
        ("", "eligibility", "text"),
        ("", "beneficiary_type", "string(30)"),
        ("", "amount_type", "string(20)"),
        ("", "amount", "decimal(12,2) NULL"),
        ("", "amount_max", "decimal(12,2) NULL"),
        ("", "is_open", "boolean"),
        ("", "open_from / open_until", "date NULL"),
        ("", "slot_limit", "uint NULL"),
        ("", "sort_order", "uint"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "program_requirements": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("FK", "assistance_program_id", "bigint  cascade"),
        ("", "name", "string"),
        ("", "description", "text NULL"),
        ("", "is_required", "boolean"),
        ("", "ocr_fields", "json NULL"),
        ("", "sort_order", "uint"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "program_eligibility_rules": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("FK", "assistance_program_id", "bigint  cascade"),
        ("", "label", "string"),
        ("", "field", "string NULL"),
        ("", "operator", "string(30) NULL"),
        ("", "value", "string NULL"),
        ("", "check_mode", "string(20) default ocr"),
        ("", "sort_order", "uint"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "program_form_fields": (COLORS["program"], [
        ("PK", "id", "bigint"),
        ("FK", "assistance_program_id", "bigint  cascade"),
        ("", "name", "string"),
        ("", "label", "string"),
        ("", "type", "string(30)"),
        ("", "options", "json NULL"),
        ("", "is_required", "boolean"),
        ("", "help_text", "text NULL"),
        ("", "sort_order", "uint"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "applications": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("UK", "application_no", "string"),
        ("FK", "applicant_id", "bigint  restrict"),
        ("FK", "assistance_program_id", "bigint  restrict"),
        ("", "status", "string(40)"),
        ("", "current_step", "tinyint"),
        ("", "submitted_at", "timestamp NULL"),
        ("FK", "assigned_staff_id", "bigint NULL  set null"),
        ("", "approved_amount", "decimal(12,2) NULL"),
        ("", "remarks", "text NULL"),
        ("", "created_at / updated_at", "timestamp"),
        ("", "indexes: status+submitted_at,", ""),
        ("", "applicant_id+status, program_id", ""),
    ]),
    "application_answers": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint  cascade"),
        ("FK", "program_form_field_id", "bigint NULL  set null"),
        ("", "field_name", "string"),
        ("", "field_label", "string"),
        ("", "value", "text NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "application_status_history": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint  cascade"),
        ("", "from_status", "string(40) NULL"),
        ("", "to_status", "string(40)"),
        ("FK", "user_id", "bigint NULL  set null"),
        ("", "remarks", "text NULL"),
        ("", "created_at", "timestamp"),
    ]),
    "application_evaluations": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint  cascade"),
        ("FK", "evaluator_id", "bigint  restrict"),
        ("", "eligibility_passed", "boolean"),
        ("", "eligibility_checks", "json NULL"),
        ("", "documents_complete", "boolean"),
        ("", "assessment", "text NULL"),
        ("", "recommendation", "string(30)"),
        ("", "recommended_amount", "decimal(12,2) NULL"),
        ("", "remarks", "text"),
        ("", "evaluated_at", "timestamp NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "application_approvals": (COLORS["application"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint  cascade"),
        ("FK", "officer_id", "bigint  restrict"),
        ("", "decision", "string(30)"),
        ("", "approved_amount", "decimal(12,2) NULL"),
        ("", "remarks", "text"),
        ("", "decided_at", "timestamp NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "document_submissions": (COLORS["document"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint  cascade"),
        ("FK", "program_requirement_id", "bigint NULL  set null"),
        ("", "requirement_name", "string"),
        ("", "file_path", "string"),
        ("", "original_name", "string"),
        ("", "mime_type", "string(120) NULL"),
        ("", "file_size", "uint NULL"),
        ("", "uploaded_at", "timestamp NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "document_verifications": (COLORS["document"], [
        ("PK", "id", "bigint"),
        ("UK", "document_submission_id", "bigint FK  cascade"),
        ("", "status", "string(40) default pending"),
        ("FK", "verified_by", "bigint NULL  set null"),
        ("", "verified_at", "timestamp NULL"),
        ("", "remarks", "text NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "ocr_results": (COLORS["document"], [
        ("PK", "id", "bigint"),
        ("UK", "document_submission_id", "bigint FK  cascade"),
        ("", "status", "string(30) default pending"),
        ("", "raw_text", "longtext NULL"),
        ("", "expected_type", "string(80) NULL"),
        ("", "detected_type", "string(80) NULL"),
        ("", "type_matches", "boolean NULL"),
        ("", "overall_score", "tinyint NULL"),
        ("", "overall_status", "string(30) NULL"),
        ("", "summary", "string NULL"),
        ("", "error_message", "text NULL"),
        ("", "processed_at", "timestamp NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "ocr_extracted_fields": (COLORS["document"], [
        ("PK", "id", "bigint"),
        ("FK", "ocr_result_id", "bigint  cascade"),
        ("", "field_key", "string(60)"),
        ("", "field_label", "string"),
        ("", "expected_value", "text NULL"),
        ("", "extracted_value", "text NULL"),
        ("", "corrected_value", "text NULL"),
        ("", "match_status", "string(30)"),
        ("", "match_score", "tinyint NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "release_schedules": (COLORS["release"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint  restrict"),
        ("", "release_date", "date"),
        ("", "release_location", "string"),
        ("", "release_method", "string(40)"),
        ("", "status", "string(30) default scheduled"),
        ("FK", "scheduled_by", "bigint  restrict"),
        ("", "notes", "text NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "assistance_releases": (COLORS["release"], [
        ("PK", "id", "bigint"),
        ("FK", "application_id", "bigint  restrict"),
        ("FK", "release_schedule_id", "bigint NULL  set null"),
        ("", "amount", "decimal(12,2)"),
        ("", "released_at", "timestamp NULL"),
        ("FK", "released_by", "bigint  restrict"),
        ("UK", "reference_no", "string"),
        ("UK", "verification_code", "string"),
        ("", "remarks", "text NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "release_verifications": (COLORS["release"], [
        ("PK", "id", "bigint"),
        ("FK", "assistance_release_id", "bigint  cascade"),
        ("FK", "verified_by", "bigint  restrict"),
        ("", "verified_at", "timestamp NULL"),
        ("", "result", "string(40)"),
        ("", "lookup_method", "string(40) NULL"),
        ("", "remarks", "text NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "announcements": (COLORS["comms"], [
        ("PK", "id", "bigint"),
        ("", "title", "string"),
        ("", "type", "string(40)"),
        ("", "body", "text"),
        ("", "is_published", "boolean"),
        ("", "published_at", "timestamp NULL"),
        ("FK", "author_id", "bigint  restrict"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "system_notifications": (COLORS["comms"], [
        ("PK", "id", "bigint"),
        ("FK", "user_id", "bigint  cascade"),
        ("", "title", "string"),
        ("", "body", "text"),
        ("", "type", "string(40) default info"),
        ("FK", "application_id", "bigint NULL  set null"),
        ("", "read_at", "timestamp NULL"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "audit_logs": (COLORS["system"], [
        ("PK", "id", "bigint"),
        ("FK", "user_id", "bigint NULL  set null"),
        ("", "action", "string(40)"),
        ("FK", "application_id", "bigint NULL  set null"),
        ("", "subject_type", "string NULL"),
        ("", "subject_id", "bigint NULL"),
        ("", "ip_address", "string(45) NULL"),
        ("", "user_agent", "string NULL"),
        ("", "description", "text"),
        ("", "created_at", "timestamp"),
    ]),
    "system_settings": (COLORS["system"], [
        ("PK", "id", "bigint"),
        ("UK", "key", "string"),
        ("", "value", "text NULL"),
        ("", "group", "string default general"),
        ("", "created_at / updated_at", "timestamp"),
    ]),
    "sessions": (COLORS["system"], [
        ("PK", "id", "string"),
        ("FK", "user_id", "bigint NULL"),
        ("", "ip_address", "string(45) NULL"),
        ("", "user_agent", "text NULL"),
        ("", "payload", "longtext"),
        ("", "last_activity", "int  indexed"),
    ]),
    "password_reset_tokens": (COLORS["system"], [
        ("PK", "email", "string"),
        ("", "token", "string"),
        ("", "created_at", "timestamp NULL"),
    ]),
}


def col_x(i: int, start: int = 40) -> int:
    return start + i * (ENT_W + COL_GAP)


def pack(page: Page, catalog: dict, columns: list[tuple[float, list[str]]], y0: float, w: float, gap: float = 28) -> dict[str, str]:
    ids: dict[str, str] = {}
    for x, keys in columns:
        y = y0
        for key in keys:
            color, attrs = catalog[key]
            ids[key] = page.entity(key, attrs, x, y, color, w)
            y += page.pos[ids[key]][3] + gap
    return ids


AUTH = COLORS["auth"]
APPL = COLORS["applicant"]
PROG = COLORS["program"]
APP = COLORS["application"]
DOC = COLORS["document"]
RELZ = COLORS["release"]
COMM = COLORS["comms"]
SYS = COLORS["system"]
STAFF = "#4338CA"
OCR = "#A21CAF"


def add_relationships(page: Page, e: dict[str, str]) -> None:
    page.rel(e["roles"], e["role_permissions"], "role_id", "1n", color=AUTH)
    page.rel(e["permissions"], e["role_permissions"], "permission_id", "1n", color="#0369A1")
    page.rel(e["roles"], e["users"], "role_id", "1n", optional=True, color=AUTH)
    page.rel(e["users"], e["workflow_staff"], "user_id", "1n", color=STAFF)
    page.rel(e["users"], e["applicants"], "user_id", "11", optional=True, color=APPL)
    page.rel(e["applicants"], e["applicant_profiles"], "applicant_id", "11", optional=True, color=APPL)
    page.rel(e["applicants"], e["applicant_addresses"], "applicant_id", "1n", color="#0E7490")
    page.rel(e["program_categories"], e["assistance_programs"], "program_category_id", "1n", color=PROG)
    page.rel(e["assistance_programs"], e["program_requirements"], "assistance_program_id", "1n", color=PROG)
    page.rel(e["assistance_programs"], e["program_eligibility_rules"], "assistance_program_id", "1n", color="#2563EB")
    page.rel(e["assistance_programs"], e["program_form_fields"], "assistance_program_id", "1n", color="#1E40AF")
    page.rel(e["applicants"], e["applications"], "applicant_id", "1n", color=APPL)
    page.rel(e["assistance_programs"], e["applications"], "assistance_program_id", "1n", color=PROG)
    page.rel(e["users"], e["applications"], "assigned_staff_id", "1n", optional=True, color=STAFF)
    page.rel(e["applications"], e["application_answers"], "application_id", "1n", color=APP)
    page.rel(e["program_form_fields"], e["application_answers"], "program_form_field_id", "1n", optional=True, color="#1E40AF")
    page.rel(e["applications"], e["application_status_history"], "application_id", "1n", color="#C2410C")
    page.rel(e["users"], e["application_status_history"], "user_id", "1n", optional=True, color=STAFF)
    page.rel(e["applications"], e["application_evaluations"], "application_id", "1n", color=APP)
    page.rel(e["users"], e["application_evaluations"], "evaluator_id", "1n", color="#6366F1")
    page.rel(e["applications"], e["application_approvals"], "application_id", "1n", color="#B45309")
    page.rel(e["users"], e["application_approvals"], "officer_id", "1n", color="#4F46E5")
    page.rel(e["applications"], e["document_submissions"], "application_id", "1n", color=DOC)
    page.rel(e["program_requirements"], e["document_submissions"], "program_requirement_id", "1n", optional=True, color=PROG)
    page.rel(e["document_submissions"], e["document_verifications"], "document_submission_id", "11", optional=True, color=DOC)
    page.rel(e["users"], e["document_verifications"], "verified_by", "1n", optional=True, color=STAFF)
    page.rel(e["document_submissions"], e["ocr_results"], "document_submission_id", "11", optional=True, color=OCR)
    page.rel(e["ocr_results"], e["ocr_extracted_fields"], "ocr_result_id", "1n", color=OCR)
    page.rel(e["applications"], e["release_schedules"], "application_id", "1n", color=RELZ)
    page.rel(e["users"], e["release_schedules"], "scheduled_by", "1n", color="#15803D")
    page.rel(e["applications"], e["assistance_releases"], "application_id", "1n", color=RELZ)
    page.rel(e["release_schedules"], e["assistance_releases"], "release_schedule_id", "1n", optional=True, color="#166534")
    page.rel(e["users"], e["assistance_releases"], "released_by", "1n", color="#15803D")
    page.rel(e["assistance_releases"], e["release_verifications"], "assistance_release_id", "1n", color=RELZ)
    page.rel(e["users"], e["release_verifications"], "verified_by", "1n", color="#15803D")
    page.rel(e["users"], e["announcements"], "author_id", "1n", color=COMM)
    page.rel(e["users"], e["system_notifications"], "user_id", "1n", color=COMM)
    page.rel(e["applications"], e["system_notifications"], "application_id", "1n", optional=True, color="#DB2777")
    page.rel(e["users"], e["audit_logs"], "user_id", "1n", optional=True, color=SYS)
    page.rel(e["applications"], e["audit_logs"], "application_id", "1n", optional=True, color="#64748B")


def page_overview() -> Page:
    xs = [col_x(i) for i in range(7)]
    p = Page("1. Logical ERD", 2760, 2100)
    p.top_bus = 136
    p.box("  LYDO Nabua CAMS  ·  Logical Entity-Relationship Diagram  (Crow’s Foot)", 0, 0, 2760, 56, BANNER)
    p.box(
        "Municipality of Nabua  ·  28 domain tables  ·  PK gold  ·  FK blue  ·  Unique green  ·  Optional FK shown as ○|—  (zero-or-one)  ·  Connectors stay in column gutters and do not cross tables",
        40,
        64,
        2200,
        32,
        SUB,
    )
    p.box(
        "Auth / users   Teal applicant   Blue programs   Amber applications   Violet documents   Green releases   Pink comms   Grey system",
        40,
        94,
        2000,
        28,
        SUB,
    )
    ids = pack(
        p,
        OVERVIEW,
        [
            (xs[0], ["roles", "permissions", "role_permissions", "workflow_staff", "system_settings"]),
            (xs[1], ["users", "applicants", "applicant_profiles", "applicant_addresses"]),
            (xs[2], ["program_categories", "assistance_programs", "program_requirements", "program_eligibility_rules", "program_form_fields"]),
            (xs[3], ["applications", "application_answers", "application_status_history", "application_evaluations", "application_approvals"]),
            (xs[4], ["document_submissions", "document_verifications", "ocr_results", "ocr_extracted_fields"]),
            (xs[5], ["release_schedules", "assistance_releases", "release_verifications"]),
            (xs[6], ["announcements", "system_notifications", "audit_logs"]),
        ],
        220,
        ENT_W,
        50,
    )
    add_relationships(p, ids)
    max_y = max(y + h for _, y, _, h in p.pos.values())
    bus_bottom = p._bottom_bus() + 10 * 7 + 20
    legend_y = max(max_y + 40, bus_bottom)
    p.box(
        "Cardinality:  |— exactly one     ○|— zero or one (nullable FK)     —< one to many     ○< zero to many\n"
        "Hub entity: applications     IDs: CAMS- / BEN- / REL-     M:N roles↔permissions via role_permissions     Polymorphic: audit_logs.subject_type + subject_id\n"
        "Connector colours match the subject area: navy auth · indigo staff FKs · teal applicants · blue programs · amber applications · violet documents · magenta OCR · green releases · pink comms · grey audit.",
        40,
        legend_y,
        2680,
        80,
        LEGEND_BOX,
    )
    p.height = int(legend_y + 120)
    p.width = 2760
    return p


def page_physical() -> Page:
    xs = [col_x(i) for i in range(7)]
    p = Page("2. Physical ERD", 2760, 2800)
    p.top_bus = 136
    p.box("  LYDO Nabua CAMS  ·  Physical schema  (all columns, FK delete actions)", 0, 0, 2760, 56, BANNER)
    p.box(
        "Delete actions on FK rows: cascade · restrict · set null. Laravel framework tables (sessions, password_reset_tokens) are at the bottom-right. cache and jobs tables are omitted. Connectors stay in gutters and outer channels.",
        40,
        64,
        2400,
        32,
        SUB,
    )
    ids = pack(
        p,
        PHYSICAL,
        [
            (xs[0], ["roles", "permissions", "role_permissions", "workflow_staff", "announcements"]),
            (xs[1], ["users", "applicants", "applicant_profiles", "applicant_addresses"]),
            (xs[2], ["program_categories", "assistance_programs", "program_requirements", "program_eligibility_rules", "program_form_fields"]),
            (xs[3], ["applications", "application_answers", "application_status_history", "application_evaluations", "application_approvals"]),
            (xs[4], ["document_submissions", "document_verifications", "ocr_results", "ocr_extracted_fields"]),
            (xs[5], ["release_schedules", "assistance_releases", "release_verifications", "system_notifications"]),
            (xs[6], ["audit_logs", "system_settings", "sessions", "password_reset_tokens"]),
        ],
        220,
        ENT_W,
        40,
    )
    add_relationships(p, ids)
    p.rel(ids["users"], ids["sessions"], "user_id", "1n", optional=True, color=SYS)
    max_y = max(y + h for _, y, _, h in p.pos.values())
    p.height = int(max(max_y, p._bottom_bus() + 80) + 80)
    p.width = 2760
    return p


def page_notes() -> Page:
    p = Page("3. Keys, enums and rules", 1600, 1400)
    p.box("  LYDO Nabua CAMS  ·  Identifiers, enumerations, and integrity rules", 0, 0, 1600, 56, BANNER)

    p.box(
        "IDENTIFIERS\n\n"
        "applications.application_no   CAMS-{YEAR}-{6-digit seq}   unique\n"
        "applicants.applicant_no         BEN-{YEAR}-{6-digit seq}    unique, 1:1 with users\n"
        "assistance_releases.reference_no   REL-{YEAR}-{seq}         unique\n"
        "assistance_releases.verification_code   unique QR / claim code\n"
        "assistance_programs.code / slug   unique (e.g. EDU-001, MED-001)\n"
        "users.email, roles.slug, permissions.slug, system_settings.key   unique",
        40,
        80,
        740,
        220,
        NOTE,
    )
    p.box(
        "ENUMERATIONS (stored as strings)\n\n"
        "users via roles.slug: administrator | staff | applicant\n"
        "applications.status: draft, accepted, submitted, under_verification,\n"
        "  incomplete, under_evaluation, for_approval, approved, rejected,\n"
        "  for_revision, scheduled_for_release, released, completed, cancelled\n"
        "document_verifications.status: pending | verified | rejected | revision_requested\n"
        "workflow_staff.workflow_step: verification | evaluation | approval\n"
        "beneficiary_type: student | non_student | both (programs)\n"
        "release_method: cash | bank_transfer | ewallet | check | other\n"
        "evaluation.recommendation / approval.decision: approval/approved | rejection/rejected | revision\n"
        "eligibility check_mode: ocr | manual\n"
        "ocr overall_status: matched | mismatch | review | type_mismatch | failed | extracted",
        800,
        80,
        760,
        280,
        NOTE,
    )
    p.box(
        "CARDINALITY HIGHLIGHTS\n\n"
        "User 1 — 0..1 Applicant          (staff have no applicant row)\n"
        "Applicant 1 — 0..1 Profile       (created with the account)\n"
        "Applicant 1 — * Addresses        (one flagged is_primary)\n"
        "Applicant 1 — * Applications     (restrict delete; one current non-terminal app)\n"
        "Program 1 — * Applications       (restrict delete)\n"
        "Application 1 — * Answers, Documents, History, Evaluations, Approvals, Schedules, Releases\n"
        "Document 1 — 0..1 Verification   (unique FK)\n"
        "Document 1 — 0..1 OcrResult      (unique FK)\n"
        "OcrResult 1 — * Extracted fields\n"
        "Role * — * Permission            (role_permissions)\n"
        "User 1 — * WorkflowStaff         (unique per step)\n"
        "AuditLog optional morph          (subject_type, subject_id)",
        40,
        320,
        740,
        320,
        NOTE,
    )
    p.box(
        "FOREIGN KEY DELETE RULES\n\n"
        "CASCADE — dependent rows go with the parent\n"
        "  applicant ↔ user, profile, addresses\n"
        "  program → requirements, rules, form fields\n"
        "  application → answers, documents, history, evaluations, approvals, notifications\n"
        "  document → verification, OCR result → extracted fields\n"
        "  release → release_verifications; role_permissions; workflow_staff\n\n"
        "RESTRICT — cannot delete parent while children exist\n"
        "  category → programs; applicant/program → applications\n"
        "  evaluator, officer, scheduled_by, released_by, author, verified_by (claim)\n\n"
        "SET NULL — keep the child, clear the FK\n"
        "  users.role_id, assigned_staff_id, verified_by (documents)\n"
        "  program_form_field_id, program_requirement_id on submissions\n"
        "  application_id on notifications and audit_logs\n"
        "  release_schedule_id on assistance_releases",
        800,
        380,
        760,
        360,
        NOTE,
    )
    p.box(
        "SUBJECT AREAS (header colours)\n\n"
        "Navy     Authentication, roles, workflow staff\n"
        "Teal     Applicants, profiles, addresses\n"
        "Blue     Program catalogue, requirements, rules, form fields\n"
        "Amber    Applications and processing records\n"
        "Violet   Uploaded files, verification, OCR\n"
        "Green    Release schedules, payouts, claim checks\n"
        "Pink     Announcements and in-app notifications\n"
        "Grey     Audit log and system settings",
        40,
        660,
        740,
        240,
        LEGEND_BOX,
    )
    p.box(
        "NOT ON THIS DIAGRAM\n\n"
        "cache, cache_locks, jobs, job_batches, failed_jobs\n"
        "(Laravel infrastructure — not domain data)\n\n"
        "Source of truth: database/migrations\n"
        "especially 2024_01_01_000003_create_cams_tables.php\n"
        "plus later OCR, PWD, pending_account, and check_mode migrations.",
        800,
        760,
        760,
        180,
        LEGEND_BOX,
    )
    return p


def main() -> None:
    pages = [
        ("logical", page_overview()),
        ("physical", page_physical()),
        ("notes", page_notes()),
    ]
    inner = "\n".join(page.xml(did) for did, page in pages)
    xml = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<mxfile host="app.diagrams.net" agent="CAMS ERD generator" version="22.1.0" type="device">\n'
        f"{inner}\n"
        "</mxfile>\n"
    )
    with open(OUT, "w", encoding="utf-8") as f:
        f.write(xml)
    print(f"Wrote {OUT} ({len(xml):,} bytes, {len(pages)} pages)")


if __name__ == "__main__":
    main()
