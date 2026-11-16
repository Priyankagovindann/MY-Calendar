import { useState, useEffect, useMemo } from "react";
import {
  Plus, Trash2, Pencil, Check, X, ChevronLeft, ChevronRight,
  CalendarDays, ListChecks, Wallet, TrendingUp, TrendingDown, Clock,
} from "lucide-react";

// ---------- constants ----------
const MONTH_NAMES = ["January","February","March","April","May","June","July","August","September","October","November","December"];
const DAY_LETTERS = ["S","M","T","W","T","F","S"];
const CATEGORIES = ["Food","Transport","Bills","Shopping","Entertainment","Health","Salary","Freelance","Other"];
const PRIORITIES = ["Low","Medium","High"];
const STORAGE_KEY = "planner-data-v1";

const pad = (n) => String(n).padStart(2, "0");
const toKey = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
const todayObj = () => { const t = new Date(); return { y: t.getFullYear(), m: t.getMonth(), d: t.getDate() }; };
const daysInMonth = (y, m) => new Date(y, m + 1, 0).getDate();
const firstWeekday = (y, m) => new Date(y, m, 1).getDay();
const fmtMoney = (n) => "\u20B9" + Math.abs(n).toLocaleString("en-IN", { maximumFractionDigits: 0 });
const uid = () => Math.random().toString(36).slice(2, 10);
const cmpKey = (a, b) => (a < b ? -1 : a > b ? 1 : 0);

function niceDate(key) {
  const [y, m, d] = key.split("-").map(Number);
  const dt = new Date(y, m - 1, d);
  return dt.toLocaleDateString("en-IN", { weekday: "short", day: "numeric", month: "short" });
}

export default function PlannerDashboard() {
  const [loading, setLoading] = useState(true);
  const [todos, setTodos] = useState([]);
  const [events, setEvents] = useState([]);
  const [expenses, setExpenses] = useState([]);

  const t = todayObj();
  const [view, setView] = useState("month"); // month | day | year
  const [cursor, setCursor] = useState({ y: t.y, m: t.m }); // month/year being displayed
  const [selectedKey, setSelectedKey] = useState(toKey(t.y, t.m, t.d));
  const [dayCursorKey, setDayCursorKey] = useState(toKey(t.y, t.m, t.d));

  const [modal, setModal] = useState(null); // { type: 'todo'|'event', editing: obj|null, date }
  const [expenseForm, setExpenseForm] = useState({ open: false, editing: null, type: "expense", amount: "", category: CATEGORIES[0], date: toKey(t.y, t.m, t.d), note: "" });

  // ---------- load ----------
  useEffect(() => {
    (async () => {
      try {
        const res = await window.storage.get(STORAGE_KEY, false);
        if (res && res.value) {
          const parsed = JSON.parse(res.value);
          setTodos(parsed.todos || []);
          setEvents(parsed.events || []);
          setExpenses(parsed.expenses || []);
        }
      } catch (e) {
        // no existing data yet, start fresh
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const persist = async (next) => {
    try {
      await window.storage.set(STORAGE_KEY, JSON.stringify({
        todos: next.todos ?? todos,
        events: next.events ?? events,
        expenses: next.expenses ?? expenses,
      }), false);
    } catch (e) {
      console.error("Could not save planner data", e);
    }
  };

  // ---------- derived ----------
  const itemsByDate = useMemo(() => {
    const map = {};
    const ensure = (k) => (map[k] ||= { todos: [], events: [], expenses: [] });
    todos.forEach((x) => ensure(x.date).todos.push(x));
    events.forEach((x) => ensure(x.date).events.push(x));
    expenses.forEach((x) => ensure(x.date).expenses.push(x));
    return map;
  }, [todos, events, expenses]);

  const totals = useMemo(() => {
    const income = expenses.filter((e) => e.type === "income").reduce((s, e) => s + Number(e.amount), 0);
    const spent = expenses.filter((e) => e.type === "expense").reduce((s, e) => s + Number(e.amount), 0);
    const byCategory = {};
    expenses.filter((e) => e.type === "expense").forEach((e) => { byCategory[e.category] = (byCategory[e.category] || 0) + Number(e.amount); });
    return { income, spent, balance: income - spent, byCategory };
  }, [expenses]);

  const upcomingEvents = useMemo(() => [...events].sort((a, b) => cmpKey(a.date + (a.time||""), b.date + (b.time||""))).filter(e => e.date >= toKey(t.y,t.m,t.d)).slice(0, 6), [events]);
  const pendingTodos = useMemo(() => [...todos].sort((a, b) => cmpKey(a.date, b.date)), [todos]);
  const recentExpenses = useMemo(() => [...expenses].sort((a, b) => cmpKey(b.date, a.date)).slice(0, 8), [expenses]);

  // ---------- CRUD: todo ----------
  const saveTodo = (data) => {
    let next;
    if (data.id) next = todos.map((x) => (x.id === data.id ? data : x));
    else next = [...todos, { ...data, id: uid(), done: false }];
    setTodos(next); persist({ todos: next });
  };
  const toggleTodo = (id) => { const next = todos.map((x) => x.id === id ? { ...x, done: !x.done } : x); setTodos(next); persist({ todos: next }); };
  const deleteTodo = (id) => { const next = todos.filter((x) => x.id !== id); setTodos(next); persist({ todos: next }); };

  // ---------- CRUD: event ----------
  const saveEvent = (data) => {
    let next;
    if (data.id) next = events.map((x) => (x.id === data.id ? data : x));
    else next = [...events, { ...data, id: uid() }];
    setEvents(next); persist({ events: next });
  };
  const deleteEvent = (id) => { const next = events.filter((x) => x.id !== id); setEvents(next); persist({ events: next }); };

  // ---------- CRUD: expense ----------
  const saveExpense = () => {
    const amt = Number(expenseForm.amount);
    if (!amt || amt <= 0 || !expenseForm.date) return;
    const payload = { type: expenseForm.type, amount: amt, category: expenseForm.category, date: expenseForm.date, note: expenseForm.note };
    let next;
    if (expenseForm.editing) next = expenses.map((x) => x.id === expenseForm.editing ? { ...payload, id: x.id } : x);
    else next = [...expenses, { ...payload, id: uid() }];
    setExpenses(next); persist({ expenses: next });
    setExpenseForm({ open: false, editing: null, type: "expense", amount: "", category: CATEGORIES[0], date: expenseForm.date, note: "" });
  };
  const editExpense = (x) => setExpenseForm({ open: true, editing: x.id, type: x.type, amount: String(x.amount), category: x.category, date: x.date, note: x.note || "" });
  const deleteExpense = (id) => { const next = expenses.filter((x) => x.id !== id); setExpenses(next); persist({ expenses: next }); };

  // ---------- calendar helpers ----------
  const openMonth = (y, m) => { setCursor({ y, m }); setView("month"); };
  const gridFor = (y, m) => {
    const first = firstWeekday(y, m);
    const total = daysInMonth(y, m);
    const prevTotal = daysInMonth(y, m === 0 ? 11 : m - 1);
    const cells = [];
    for (let i = 0; i < first; i++) cells.push({ inMonth: false, y: m === 0 ? y - 1 : y, m: m === 0 ? 11 : m - 1, d: prevTotal - first + 1 + i });
    for (let d = 1; d <= total; d++) cells.push({ inMonth: true, y, m, d });
    while (cells.length % 7 !== 0 || cells.length < 42) {
      const last = cells[cells.length - 1];
      const nm = last.m === 11 ? 0 : last.m + 1, ny = last.m === 11 ? last.y + 1 : last.y;
      cells.push({ inMonth: false, y: ny, m: nm, d: (cells.length - (first + total)) + 1 });
    }
    return cells;
  };

  if (loading) {
    return (
      <div className="w-full h-full flex items-center justify-center py-24" style={{ color: "var(--ink-soft)" }}>
        Loading your planner…
      </div>
    );
  }

  return (
    <div style={ROOT_VARS} className="w-full min-h-full" >
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap');
        .planner-root, .planner-root * { box-sizing: border-box; }
        .planner-root { font-family: 'IBM Plex Sans', sans-serif; background: var(--paper); color: var(--ink); }
        .font-display { font-family: 'Fraunces', serif; }
        .font-mono { font-family: 'IBM Plex Mono', monospace; }
        .card { background: var(--paper-alt); border: 1px solid var(--rule); }
        .btn-plain:focus-visible, .day-cell:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }
      `}</style>

      <div className="planner-root max-w-6xl mx-auto px-4 sm:px-6 py-6">
        <Header onAddTask={() => setModal({ type: "todo", editing: null, date: selectedKey })} />

        <div className="mt-6 flex flex-col lg:flex-row gap-6">
          {/* LEFT: Calendar */}
          <div className="lg:w-[62%] flex flex-col gap-4">
            <ViewSwitcher view={view} setView={setView} />

            {view === "month" && (
              <MonthView
                cursor={cursor} setCursor={setCursor}
                itemsByDate={itemsByDate}
                selectedKey={selectedKey} setSelectedKey={setSelectedKey}
                gridFor={gridFor}
              />
            )}
            {view === "day" && (
              <DayView
                dayCursorKey={dayCursorKey} setDayCursorKey={setDayCursorKey}
                itemsByDate={itemsByDate}
                onAdd={(type) => setModal({ type, editing: null, date: dayCursorKey })}
                onEditTodo={(x) => setModal({ type: "todo", editing: x, date: x.date })}
                onEditEvent={(x) => setModal({ type: "event", editing: x, date: x.date })}
                toggleTodo={toggleTodo} deleteTodo={deleteTodo} deleteEvent={deleteEvent}
              />
            )}
            {view === "year" && (
              <YearView year={cursor.y} setYear={(y) => setCursor((c) => ({ ...c, y }))} itemsByDate={itemsByDate} onPickMonth={(m) => openMonth(cursor.y, m)} />
            )}

            {view === "month" && (
              <DayAgenda
                selectedKey={selectedKey}
                itemsByDate={itemsByDate}
                onAdd={(type) => setModal({ type, editing: null, date: selectedKey })}
                onEditTodo={(x) => setModal({ type: "todo", editing: x, date: x.date })}
                onEditEvent={(x) => setModal({ type: "event", editing: x, date: x.date })}
                toggleTodo={toggleTodo} deleteTodo={deleteTodo} deleteEvent={deleteEvent}
              />
            )}
          </div>

          {/* RIGHT: Modules */}
          <div className="lg:w-[38%] flex flex-col gap-5">
            <EventsPanel
              events={upcomingEvents}
              onAdd={() => setModal({ type: "event", editing: null, date: selectedKey })}
              onEdit={(x) => setModal({ type: "event", editing: x, date: x.date })}
              onDelete={deleteEvent}
            />
            <TodoPanel
              todos={pendingTodos}
              onAdd={() => setModal({ type: "todo", editing: null, date: selectedKey })}
              onEdit={(x) => setModal({ type: "todo", editing: x, date: x.date })}
              onToggle={toggleTodo}
              onDelete={deleteTodo}
            />
            <ExpensesPanel
              totals={totals}
              recent={recentExpenses}
              form={expenseForm} setForm={setExpenseForm}
              onSave={saveExpense} onEdit={editExpense} onDelete={deleteExpense}
            />
          </div>
        </div>
      </div>

      {modal && (
        <TaskModal
          modal={modal} onClose={() => setModal(null)}
          onSaveTodo={(d) => { saveTodo(d); setModal(null); }}
          onSaveEvent={(d) => { saveEvent(d); setModal(null); }}
        />
      )}
    </div>
  );
}

const ROOT_VARS = {
  "--paper": "#EFEAE0",
  "--paper-alt": "#F8F5EE",
  "--ink": "#26313D",
  "--ink-soft": "#6B7280",
  "--rule": "#D2C9B6",
  "--gold": "#B08B37",
  "--gold-soft": "#EEDFB8",
  "--green": "#4B6B4F",
  "--green-soft": "#E1E8DD",
  "--rust": "#9C4A3A",
  "--rust-soft": "#F1E0DB",
};

// ================= Header =================
function Header({ onAddTask }) {
  const today = new Date();
  return (
    <div className="flex items-start sm:items-center justify-between flex-col sm:flex-row gap-3">
      <div>
        <h1 className="font-display text-3xl sm:text-4xl" style={{ color: "var(--ink)" }}>Almanac</h1>
        <p className="text-sm mt-1" style={{ color: "var(--ink-soft)" }}>
          {today.toLocaleDateString("en-IN", { weekday: "long", day: "numeric", month: "long", year: "numeric" })}
        </p>
      </div>
      <button
        onClick={onAddTask}
        className="flex items-center gap-2 px-4 py-2.5 rounded-sm font-medium text-sm transition-colors"
        style={{ background: "var(--ink)", color: "var(--paper-alt)" }}
      >
        <Plus size={16} /> Add task
      </button>
    </div>
  );
}

// ================= View switcher =================
function ViewSwitcher({ view, setView }) {
  const opts = [
    { k: "month", label: "Month" },
    { k: "day", label: "Day" },
    { k: "year", label: "Year" },
  ];
  return (
    <div className="flex gap-1 p-1 rounded-sm w-fit" style={{ background: "var(--paper-alt)", border: "1px solid var(--rule)" }}>
      {opts.map((o) => (
        <button
          key={o.k}
          onClick={() => setView(o.k)}
          className="px-3 py-1.5 text-sm rounded-sm transition-colors"
          style={view === o.k ? { background: "var(--ink)", color: "var(--paper-alt)" } : { color: "var(--ink-soft)" }}
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}

// ================= Month view =================
function MonthView({ cursor, setCursor, itemsByDate, selectedKey, setSelectedKey, gridFor }) {
  const cells = gridFor(cursor.y, cursor.m);
  const goto = (delta) => {
    let m = cursor.m + delta, y = cursor.y;
    if (m < 0) { m = 11; y -= 1; } if (m > 11) { m = 0; y += 1; }
    setCursor({ y, m });
  };
  const t = todayObj();

  return (
    <div className="card rounded-sm p-4">
      <div className="flex items-center justify-between mb-4">
        <h2 className="font-display text-xl" style={{ color: "var(--ink)" }}>{MONTH_NAMES[cursor.m]} {cursor.y}</h2>
        <div className="flex items-center gap-1">
          <button onClick={() => goto(-1)} className="p-1.5 rounded-sm" style={{ color: "var(--ink-soft)" }}><ChevronLeft size={18} /></button>
          <button onClick={() => setCursor({ y: t.y, m: t.m })} className="px-2.5 py-1 text-xs rounded-sm" style={{ color: "var(--ink-soft)", border: "1px solid var(--rule)" }}>Today</button>
          <button onClick={() => goto(1)} className="p-1.5 rounded-sm" style={{ color: "var(--ink-soft)" }}><ChevronRight size={18} /></button>
        </div>
      </div>

      <div className="grid grid-cols-7 gap-1 mb-1">
        {DAY_LETTERS.map((d, i) => (
          <div key={i} className="text-center text-xs py-1" style={{ color: "var(--ink-soft)" }}>{d}</div>
        ))}
      </div>
      <div className="grid grid-cols-7 gap-1">
        {cells.map((c, i) => {
          const key = toKey(c.y, c.m, c.d);
          const items = itemsByDate[key];
          const isToday = c.y === t.y && c.m === t.m && c.d === t.d;
          const isSelected = key === selectedKey;
          return (
            <button
              key={i}
              onClick={() => setSelectedKey(key)}
              className="day-cell aspect-square rounded-sm flex flex-col items-center justify-start pt-1.5 gap-1 text-sm transition-colors"
              style={{
                opacity: c.inMonth ? 1 : 0.35,
                background: isSelected ? "var(--gold-soft)" : "transparent",
                border: isToday ? "1.5px solid var(--gold)" : "1px solid transparent",
                color: "var(--ink)",
              }}
            >
              <span className="font-mono">{c.d}</span>
              <span className="flex gap-0.5">
                {items?.events?.length > 0 && <Dot color="var(--rust)" />}
                {items?.todos?.length > 0 && <Dot color="var(--ink-soft)" />}
                {items?.expenses?.length > 0 && <Dot color="var(--green)" />}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

function Dot({ color }) {
  return <span className="inline-block rounded-full" style={{ width: 5, height: 5, background: color }} />;
}

// ================= Day agenda (used within month view) =================
function DayAgenda({ selectedKey, itemsByDate, onAdd, onEditTodo, onEditEvent, toggleTodo, deleteTodo, deleteEvent }) {
  const items = itemsByDate[selectedKey] || { todos: [], events: [], expenses: [] };
  const spentToday = items.expenses.filter(e => e.type === "expense").reduce((s, e) => s + Number(e.amount), 0);
  return (
    <div className="card rounded-sm p-4">
      <div className="flex items-center justify-between mb-3">
        <h3 className="font-display text-lg">{niceDate(selectedKey)}</h3>
        <div className="flex gap-2">
          <button onClick={() => onAdd("event")} className="text-xs px-2.5 py-1 rounded-sm" style={{ border: "1px solid var(--rule)", color: "var(--ink-soft)" }}>+ Event</button>
          <button onClick={() => onAdd("todo")} className="text-xs px-2.5 py-1 rounded-sm" style={{ border: "1px solid var(--rule)", color: "var(--ink-soft)" }}>+ To-do</button>
        </div>
      </div>
      {items.events.length === 0 && items.todos.length === 0 && items.expenses.length === 0 ? (
        <p className="text-sm" style={{ color: "var(--ink-soft)" }}>Nothing planned yet. Use the buttons above to add something.</p>
      ) : (
        <div className="flex flex-col gap-2">
          {items.events.map((e) => (
            <div key={e.id} className="flex items-center justify-between text-sm py-1.5 border-b" style={{ borderColor: "var(--rule)" }}>
              <div className="flex items-center gap-2 min-w-0">
                <Dot color="var(--rust)" />
                {e.time && <span className="font-mono text-xs" style={{ color: "var(--ink-soft)" }}>{e.time}</span>}
                <span className="truncate">{e.title}</span>
              </div>
              <RowActions onEdit={() => onEditEvent(e)} onDelete={() => deleteEvent(e.id)} />
            </div>
          ))}
          {items.todos.map((x) => (
            <div key={x.id} className="flex items-center justify-between text-sm py-1.5 border-b" style={{ borderColor: "var(--rule)" }}>
              <div className="flex items-center gap-2 min-w-0">
                <button onClick={() => toggleTodo(x.id)} className="w-4 h-4 rounded-sm border flex items-center justify-center shrink-0" style={{ borderColor: "var(--ink-soft)", background: x.done ? "var(--ink-soft)" : "transparent" }}>
                  {x.done && <Check size={11} color="var(--paper-alt)" />}
                </button>
                <span className={"truncate" + (x.done ? " line-through" : "")} style={{ color: x.done ? "var(--ink-soft)" : "var(--ink)" }}>{x.title}</span>
              </div>
              <RowActions onEdit={() => onEditTodo(x)} onDelete={() => deleteTodo(x.id)} />
            </div>
          ))}
          {items.expenses.length > 0 && (
            <div className="text-sm pt-1" style={{ color: "var(--ink-soft)" }}>Spent this day: <span className="font-mono" style={{ color: "var(--rust)" }}>{fmtMoney(spentToday)}</span></div>
          )}
        </div>
      )}
    </div>
  );
}

// ================= Day view (full page) =================
function DayView({ dayCursorKey, setDayCursorKey, itemsByDate, onAdd, onEditTodo, onEditEvent, toggleTodo, deleteTodo, deleteEvent }) {
  const shift = (delta) => {
    const [y, m, d] = dayCursorKey.split("-").map(Number);
    const dt = new Date(y, m - 1, d + delta);
    setDayCursorKey(toKey(dt.getFullYear(), dt.getMonth(), dt.getDate()));
  };
  const items = itemsByDate[dayCursorKey] || { todos: [], events: [], expenses: [] };
  const sortedEvents = [...items.events].sort((a, b) => cmpKey(a.time || "", b.time || ""));

  return (
    <div className="card rounded-sm p-4">
      <div className="flex items-center justify-between mb-4">
        <h2 className="font-display text-xl">{niceDate(dayCursorKey)}</h2>
        <div className="flex items-center gap-1">
          <button onClick={() => shift(-1)} className="p-1.5 rounded-sm" style={{ color: "var(--ink-soft)" }}><ChevronLeft size={18} /></button>
          <button onClick={() => { const t = todayObj(); setDayCursorKey(toKey(t.y, t.m, t.d)); }} className="px-2.5 py-1 text-xs rounded-sm" style={{ color: "var(--ink-soft)", border: "1px solid var(--rule)" }}>Today</button>
          <button onClick={() => shift(1)} className="p-1.5 rounded-sm" style={{ color: "var(--ink-soft)" }}><ChevronRight size={18} /></button>
        </div>
      </div>

      <div className="flex gap-2 mb-4">
        <button onClick={() => onAdd("event")} className="text-xs px-2.5 py-1.5 rounded-sm flex items-center gap-1" style={{ border: "1px solid var(--rule)", color: "var(--ink-soft)" }}><Plus size={13}/> Event</button>
        <button onClick={() => onAdd("todo")} className="text-xs px-2.5 py-1.5 rounded-sm flex items-center gap-1" style={{ border: "1px solid var(--rule)", color: "var(--ink-soft)" }}><Plus size={13}/> To-do</button>
      </div>

      <div className="mb-5">
        <p className="text-xs uppercase tracking-wide mb-2" style={{ color: "var(--ink-soft)" }}>Events</p>
        {sortedEvents.length === 0 ? <p className="text-sm" style={{ color: "var(--ink-soft)" }}>No events today.</p> : (
          <div className="flex flex-col gap-2">
            {sortedEvents.map((e) => (
              <div key={e.id} className="flex items-start justify-between text-sm py-2 px-3 rounded-sm" style={{ background: "var(--rust-soft)" }}>
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    {e.time && <span className="font-mono text-xs flex items-center gap-1" style={{color:"var(--rust)"}}><Clock size={11}/>{e.time}</span>}
                    <span className="font-medium truncate">{e.title}</span>
                  </div>
                  {e.description && <p className="text-xs mt-0.5" style={{ color: "var(--ink-soft)" }}>{e.description}</p>}
                </div>
                <RowActions onEdit={() => onEditEvent(e)} onDelete={() => deleteEvent(e.id)} />
              </div>
            ))}
          </div>
        )}
      </div>

      <div>
        <p className="text-xs uppercase tracking-wide mb-2" style={{ color: "var(--ink-soft)" }}>To-do</p>
        {items.todos.length === 0 ? <p className="text-sm" style={{ color: "var(--ink-soft)" }}>No to-dos today.</p> : (
          <div className="flex flex-col gap-2">
            {items.todos.map((x) => (
              <div key={x.id} className="flex items-center justify-between text-sm py-2 px-3 rounded-sm" style={{ background: "var(--paper)" }}>
                <div className="flex items-center gap-2 min-w-0">
                  <button onClick={() => toggleTodo(x.id)} className="w-4 h-4 rounded-sm border flex items-center justify-center shrink-0" style={{ borderColor: "var(--ink-soft)", background: x.done ? "var(--ink-soft)" : "transparent" }}>
                    {x.done && <Check size={11} color="var(--paper-alt)" />}
                  </button>
                  <span className={"truncate" + (x.done ? " line-through" : "")} style={{ color: x.done ? "var(--ink-soft)" : "var(--ink)" }}>{x.title}</span>
                  {x.priority && <span className="text-[10px] px-1.5 py-0.5 rounded-sm font-mono shrink-0" style={{ background: "var(--gold-soft)", color: "var(--gold)" }}>{x.priority}</span>}
                </div>
                <RowActions onEdit={() => onEditTodo(x)} onDelete={() => deleteTodo(x.id)} />
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

// ================= Year view =================
function YearView({ year, setYear, itemsByDate, onPickMonth }) {
  return (
    <div className="card rounded-sm p-4">
      <div className="flex items-center justify-between mb-4">
        <h2 className="font-display text-xl">{year}</h2>
        <div className="flex items-center gap-1">
          <button onClick={() => setYear(year - 1)} className="p-1.5 rounded-sm" style={{ color: "var(--ink-soft)" }}><ChevronLeft size={18} /></button>
          <button onClick={() => setYear(year + 1)} className="p-1.5 rounded-sm" style={{ color: "var(--ink-soft)" }}><ChevronRight size={18} /></button>
        </div>
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {MONTH_NAMES.map((name, m) => {
          const first = firstWeekday(year, m);
          const total = daysInMonth(year, m);
          const cells = Array.from({ length: first }, () => null).concat(Array.from({ length: total }, (_, i) => i + 1));
          const hasAny = (d) => { const k = toKey(year, m, d); const it = itemsByDate[k]; return it && (it.events.length || it.todos.length || it.expenses.length); };
          return (
            <button key={m} onClick={() => onPickMonth(m)} className="text-left p-2 rounded-sm" style={{ border: "1px solid var(--rule)" }}>
              <p className="text-xs font-medium mb-1.5" style={{ color: "var(--ink)" }}>{name}</p>
              <div className="grid grid-cols-7 gap-[2px]">
                {cells.map((d, i) => (
                  <span key={i} className="flex items-center justify-center font-mono" style={{ fontSize: 8, height: 12, color: d ? "var(--ink-soft)" : "transparent", background: d && hasAny(d) ? "var(--gold-soft)" : "transparent" }}>
                    {d || "0"}
                  </span>
                ))}
              </div>
            </button>
          );
        })}
      </div>
    </div>
  );
}

// ================= Row actions =================
function RowActions({ onEdit, onDelete }) {
  return (
    <div className="flex items-center gap-1 shrink-0 ml-2">
      <button onClick={onEdit} className="p-1 rounded-sm" style={{ color: "var(--ink-soft)" }} aria-label="Edit"><Pencil size={13} /></button>
      <button onClick={onDelete} className="p-1 rounded-sm" style={{ color: "var(--rust)" }} aria-label="Delete"><Trash2 size={13} /></button>
    </div>
  );
}

// ================= Events panel =================
function EventsPanel({ events, onAdd, onEdit, onDelete }) {
  return (
    <div className="card rounded-sm p-4">
      <PanelHeader icon={<CalendarDays size={16} color="var(--rust)" />} title="Events" onAdd={onAdd} />
      {events.length === 0 ? (
        <EmptyState text="No upcoming events. Add one to see it here." />
      ) : (
        <div className="flex flex-col gap-2">
          {events.map((e) => (
            <div key={e.id} className="flex items-start justify-between text-sm py-2 border-b last:border-0" style={{ borderColor: "var(--rule)" }}>
              <div className="min-w-0">
                <p className="text-xs font-mono" style={{ color: "var(--rust)" }}>{niceDate(e.date)}{e.time ? ` · ${e.time}` : ""}</p>
                <p className="truncate">{e.title}</p>
              </div>
              <RowActions onEdit={() => onEdit(e)} onDelete={() => onDelete(e.id)} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ================= Todo panel =================
function TodoPanel({ todos, onAdd, onEdit, onToggle, onDelete }) {
  const active = todos.filter((x) => !x.done);
  const done = todos.filter((x) => x.done);
  return (
    <div className="card rounded-sm p-4">
      <PanelHeader icon={<ListChecks size={16} color="var(--ink)" />} title="To-do" onAdd={onAdd} />
      {todos.length === 0 ? (
        <EmptyState text="Nothing on your list. Add a to-do to get started." />
      ) : (
        <div className="flex flex-col gap-1 max-h-64 overflow-y-auto pr-1">
          {[...active, ...done].map((x) => (
            <div key={x.id} className="flex items-center justify-between text-sm py-1.5 border-b last:border-0" style={{ borderColor: "var(--rule)" }}>
              <div className="flex items-center gap-2 min-w-0">
                <button onClick={() => onToggle(x.id)} className="w-4 h-4 rounded-sm border flex items-center justify-center shrink-0" style={{ borderColor: "var(--ink-soft)", background: x.done ? "var(--ink-soft)" : "transparent" }}>
                  {x.done && <Check size={11} color="var(--paper-alt)" />}
                </button>
                <span className={"truncate" + (x.done ? " line-through" : "")} style={{ color: x.done ? "var(--ink-soft)" : "var(--ink)" }}>{x.title}</span>
                <span className="text-[10px] font-mono shrink-0" style={{ color: "var(--ink-soft)" }}>{niceDate(x.date)}</span>
              </div>
              <RowActions onEdit={() => onEdit(x)} onDelete={() => onDelete(x.id)} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ================= Expenses panel =================
function ExpensesPanel({ totals, recent, form, setForm, onSave, onEdit, onDelete }) {
  return (
    <div className="card rounded-sm p-4">
      <PanelHeader
        icon={<Wallet size={16} color="var(--green)" />}
        title="Expenses"
        onAdd={() => setForm((f) => ({ ...f, open: !f.open, editing: null }))}
      />

      <div className="grid grid-cols-3 gap-2 mb-4">
        <Stat label="Income" value={fmtMoney(totals.income)} color="var(--green)" icon={<TrendingUp size={12} />} />
        <Stat label="Spent" value={fmtMoney(totals.spent)} color="var(--rust)" icon={<TrendingDown size={12} />} />
        <Stat label="Balance" value={(totals.balance < 0 ? "-" : "") + fmtMoney(totals.balance)} color="var(--ink)" />
      </div>

      {form.open && (
        <div className="p-3 rounded-sm mb-4 flex flex-col gap-2" style={{ background: "var(--paper)", border: "1px solid var(--rule)" }}>
          <div className="flex gap-1">
            {["expense", "income"].map((tp) => (
              <button key={tp} onClick={() => setForm((f) => ({ ...f, type: tp }))}
                className="flex-1 text-xs py-1.5 rounded-sm capitalize"
                style={form.type === tp ? { background: tp === "income" ? "var(--green)" : "var(--rust)", color: "var(--paper-alt)" } : { border: "1px solid var(--rule)", color: "var(--ink-soft)" }}>
                {tp}
              </button>
            ))}
          </div>
          <div className="flex gap-2">
            <input type="number" min="0" placeholder="Amount" value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))}
              className="w-1/2 text-sm px-2 py-1.5 rounded-sm font-mono" style={{ border: "1px solid var(--rule)", background: "var(--paper-alt)" }} />
            <input type="date" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))}
              className="w-1/2 text-sm px-2 py-1.5 rounded-sm font-mono" style={{ border: "1px solid var(--rule)", background: "var(--paper-alt)" }} />
          </div>
          <select value={form.category} onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}
            className="text-sm px-2 py-1.5 rounded-sm" style={{ border: "1px solid var(--rule)", background: "var(--paper-alt)" }}>
            {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
          <input type="text" placeholder="Note (optional)" value={form.note} onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))}
            className="text-sm px-2 py-1.5 rounded-sm" style={{ border: "1px solid var(--rule)", background: "var(--paper-alt)" }} />
          <div className="flex gap-2 justify-end mt-1">
            <button onClick={() => setForm((f) => ({ ...f, open: false, editing: null }))} className="text-xs px-3 py-1.5 rounded-sm" style={{ color: "var(--ink-soft)" }}>Cancel</button>
            <button onClick={onSave} className="text-xs px-3 py-1.5 rounded-sm font-medium" style={{ background: "var(--ink)", color: "var(--paper-alt)" }}>
              {form.editing ? "Update" : "Add"}
            </button>
          </div>
        </div>
      )}

      {recent.length === 0 ? (
        <EmptyState text="No transactions yet. Log your first income or expense." />
      ) : (
        <div className="flex flex-col gap-1 max-h-56 overflow-y-auto pr-1">
          {recent.map((x) => (
            <div key={x.id} className="flex items-center justify-between text-sm py-1.5 border-b last:border-0" style={{ borderColor: "var(--rule)" }}>
              <div className="min-w-0">
                <p className="truncate">{x.category}{x.note ? ` · ${x.note}` : ""}</p>
                <p className="text-[10px] font-mono" style={{ color: "var(--ink-soft)" }}>{niceDate(x.date)}</p>
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <span className="font-mono text-sm" style={{ color: x.type === "income" ? "var(--green)" : "var(--rust)" }}>
                  {x.type === "income" ? "+" : "-"}{fmtMoney(x.amount)}
                </span>
                <RowActions onEdit={() => onEdit(x)} onDelete={() => onDelete(x.id)} />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function Stat({ label, value, color, icon }) {
  return (
    <div className="p-2 rounded-sm" style={{ background: "var(--paper)" }}>
      <p className="text-[10px] flex items-center gap-1 mb-0.5" style={{ color: "var(--ink-soft)" }}>{icon}{label}</p>
      <p className="font-mono text-sm font-medium" style={{ color }}>{value}</p>
    </div>
  );
}

function PanelHeader({ icon, title, onAdd }) {
  return (
    <div className="flex items-center justify-between mb-3">
      <div className="flex items-center gap-2">
        {icon}
        <h3 className="font-display text-lg">{title}</h3>
      </div>
      <button onClick={onAdd} className="p-1.5 rounded-sm" style={{ border: "1px solid var(--rule)", color: "var(--ink-soft)" }} aria-label={`Add ${title}`}>
        <Plus size={14} />
      </button>
    </div>
  );
}

function EmptyState({ text }) {
  return <p className="text-sm py-3" style={{ color: "var(--ink-soft)" }}>{text}</p>;
}

// ================= Task modal (To-do / Event) =================
function TaskModal({ modal, onClose, onSaveTodo, onSaveEvent }) {
  const editing = modal.editing;
  const [type, setType] = useState(modal.type);
  const [title, setTitle] = useState(editing?.title || "");
  const [date, setDate] = useState(editing?.date || modal.date);
  const [time, setTime] = useState(editing?.time || "");
  const [priority, setPriority] = useState(editing?.priority || "Medium");
  const [description, setDescription] = useState(editing?.description || "");

  const canSubmit = title.trim().length > 0 && date;

  const handleSave = () => {
    if (!canSubmit) return;
    if (type === "todo") onSaveTodo({ id: editing?.id, title: title.trim(), date, priority });
    else onSaveEvent({ id: editing?.id, title: title.trim(), date, time, description: description.trim() });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{ background: "rgba(38,49,61,0.45)" }} onClick={onClose}>
      <div className="w-full max-w-sm rounded-sm p-5" style={{ background: "var(--paper-alt)", border: "1px solid var(--rule)" }} onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-display text-xl">{editing ? "Edit task" : "New task"}</h3>
          <button onClick={onClose} style={{ color: "var(--ink-soft)" }}><X size={18} /></button>
        </div>

        {!editing && (
          <div className="flex gap-1 mb-4">
            {["todo", "event"].map((tp) => (
              <button key={tp} onClick={() => setType(tp)}
                className="flex-1 text-sm py-1.5 rounded-sm capitalize"
                style={type === tp ? { background: "var(--ink)", color: "var(--paper-alt)" } : { border: "1px solid var(--rule)", color: "var(--ink-soft)" }}>
                {tp === "todo" ? "To-do" : "Event"}
              </button>
            ))}
          </div>
        )}

        <div className="flex flex-col gap-3">
          <Field label="Title">
            <input autoFocus type="text" value={title} onChange={(e) => setTitle(e.target.value)} placeholder={type === "todo" ? "e.g. Submit report" : "e.g. Team offsite"}
              className="w-full text-sm px-2.5 py-2 rounded-sm" style={{ border: "1px solid var(--rule)", background: "var(--paper)" }} />
          </Field>

          <div className="flex gap-3">
            <Field label="Date" className="flex-1">
              <input type="date" value={date} onChange={(e) => setDate(e.target.value)}
                className="w-full text-sm px-2.5 py-2 rounded-sm font-mono" style={{ border: "1px solid var(--rule)", background: "var(--paper)" }} />
            </Field>
            {type === "event" && (
              <Field label="Time" className="flex-1">
                <input type="time" value={time} onChange={(e) => setTime(e.target.value)}
                  className="w-full text-sm px-2.5 py-2 rounded-sm font-mono" style={{ border: "1px solid var(--rule)", background: "var(--paper)" }} />
              </Field>
            )}
          </div>

          {type === "todo" ? (
            <Field label="Priority">
              <select value={priority} onChange={(e) => setPriority(e.target.value)}
                className="w-full text-sm px-2.5 py-2 rounded-sm" style={{ border: "1px solid var(--rule)", background: "var(--paper)" }}>
                {PRIORITIES.map((p) => <option key={p} value={p}>{p}</option>)}
              </select>
            </Field>
          ) : (
            <Field label="Description (optional)">
              <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={2}
                className="w-full text-sm px-2.5 py-2 rounded-sm resize-none" style={{ border: "1px solid var(--rule)", background: "var(--paper)" }} />
            </Field>
          )}
        </div>

        <div className="flex justify-end gap-2 mt-5">
          <button onClick={onClose} className="text-sm px-3.5 py-2 rounded-sm" style={{ color: "var(--ink-soft)" }}>Cancel</button>
          <button onClick={handleSave} disabled={!canSubmit} className="text-sm px-3.5 py-2 rounded-sm font-medium" style={{ background: canSubmit ? "var(--ink)" : "var(--rule)", color: "var(--paper-alt)" }}>
            {editing ? "Save changes" : "Add"}
          </button>
        </div>
      </div>
    </div>
  );
}

function Field({ label, children, className = "" }) {
  return (
    <label className={"block " + className}>
      <span className="block text-xs mb-1" style={{ color: "var(--ink-soft)" }}>{label}</span>
      {children}
    </label>
  );
}