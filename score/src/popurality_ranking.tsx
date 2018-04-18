import React from "react";
import ReactDOM from "react-dom";

const name2j: { [key: string]: string } = {
    class: "職業",
    personality: "性格",
    race: "種族",
    realm1: "魔法領域1",
    realm2: "魔法領域2",
};

const column2j: { [col: string]: string } = {
    average_score: "平均スコア",
    female_count: "女性",
    male_count: "男性",
    max_score: "最大スコア",
    total_count: "計",
    winner_count: "勝利",
};

const columnOrder = [
    "total_count",
    "male_count",
    "female_count",
    "winner_count",
    "average_score",
    "max_score",
];

/**
 * 表示テーブル選択ボタンコンポーネントプロパティ
 */
interface ITableSelectButtonProps {
    /** ボタンが選択状態かどうか */
    selected: boolean;
    /** ボタンの銘板 */
    name: string;
    /** ボタンがクリックされた時に呼び出されるコールバック関数 */
    onClick: () => void;
}

/**
 * 表示テーブル選択ボタンコンポーネント
 */
// tslint:disable-next-line:max-classes-per-file
class TableSelectButton extends React.Component<ITableSelectButtonProps> {
    public shouldComponentUpdate(nextProps: ITableSelectButtonProps) {
        return this.props.selected !== nextProps.selected;
    }

    public render() {
        if (this.props.selected) {
            return (
                <b>
                    {name2j[this.props.name]}
                </b>
            );
        } else {
            return (
                <a href="javascript:void(0)" onClick={() => this.props.onClick()}>
                    {name2j[this.props.name]}
                </a>
            );
        }
    }
}

/**
 * 表示テーブル選択コンポーネントプロパティ
 */
interface ITableSelectProps {
    /** 選択中のテーブル名称 */
    selectedTableName: string;
    /**
     * 選択テーブル切替時に呼び出されるコールバック関数
     * @param name 新たに選択されたテーブル名称
     */
    onSelectChange: (name: string) => void;
}

/**
 * 表示テーブル選択コンポーネント
 */
// tslint:disable-next-line:max-classes-per-file
class TableSelector extends React.Component<ITableSelectProps> {
    public render() {
        return (
            <div>
                [ {this.renderSelectButton("race")} | {this.renderSelectButton("class")} |
                 {this.renderSelectButton("personality")} ]
                [ {this.renderSelectButton("realm1")} | {this.renderSelectButton("realm2")} ]
            </div>
        );
    }

    private renderSelectButton(name: string) {
        return <TableSelectButton
            selected={this.props.selectedTableName === name}
            name={name}
            onClick={() => this.props.onSelectChange(name)} />;
    }
}

/**
 * テーブルヘッダコンポーネントプロパティ
 */
interface ITableHeaderProps {
    /** テーブル名称 */
    tableName: string;
    /** ソートキーカラム */
    sortKeyColumn: string;
    /** ソート順 */
    sortOrder: SortOrder;
    /**
     * ヘッダをクリックした時に呼ばれるコールバック関数
     * @param clickColumn クリックしたカラムの名称
     */
    onClick: (clickColumn: string) => void;
}

/**
 * テーブルヘッダコンポーネント
 */
function TableHeader(props: ITableHeaderProps) {
    return (
        <thead>
            <tr>
                <th>{props.tableName}</th>
                {columnOrder.map((col) => {
                    let className = "sort";
                    if (props.sortKeyColumn === col) {
                        className += " ";
                        className += (props.sortOrder === SortOrder.Ascend) ? "ascend" : "descend";
                    }
                    return (
                        <th className={className} onClick={() => props.onClick(col)}>
                            {column2j[col]}
                        </th>
                    );
                })}
            </tr>
        </thead>
    );
}

/**
 * テーブル行データコンポーネントプロパティ
 */
interface ITableDataRowProps {
    /** スコアランキングのURLのリンクに渡すパラメータ */
    linkParam: string;
    /** 行データの名称（第1カラムに表示される文字列） */
    rowName: string;
    /** 行データ */
    row_data: { [key: string]: string | number };
}

/**
 * テーブル行データコンポーネント
 */
function TableDataRow(props: ITableDataRowProps) {
    return (
        <tr key={props.row_data.id}>
            <td>
                <a href={`score_ranking.php?${props.linkParam}`}>
                    {props.rowName}
                </a>
            </td>
            {columnOrder.map((col) =>
                <td className="number">{props.row_data[col]}</td>)}
        </tr>
    );
}

/**
 * ソート順序列挙体
 */
enum SortOrder {
    /** 昇順 */
    Ascend,
    /** 降順 */
    Descend,
}

/**
 * 人気データテーブルコンポーネントプロパティ
 */
interface IPopuralityTableProps {
    /** データ内容 */
    data: Array<{ [col: string]: any }>;
    /** データ名称 */
    name: string;
    /** テーブルの表示/非表示 */
    visible: boolean;
}

/**
 * 人気データテーブルコンポーネントステータス
 */
interface IPopuralityTableState {
    /** データソートのキーとするカラムの名称 */
    sortKeyColumn: string;
    /** データソートの順序 */
    sortOrder: SortOrder;
}

/**
 * 人気データテーブルコンポーネント
 */
// tslint:disable-next-line:max-classes-per-file
class PopuralityTable extends React.Component<IPopuralityTableProps, IPopuralityTableState> {
    constructor(props: IPopuralityTableProps) {
        super(props);
        this.state = {
            sortKeyColumn: columnOrder[0],
            sortOrder: SortOrder.Descend,
        };
    }

    public render() {
        if (!this.props.visible) {
            return "";
        }

        const data = this.get_sorted_data();
        const tableName = this.props.name;
        return (
            <table className="score statistics_table one_row">
                <TableHeader
                    tableName={name2j[tableName]}
                    sortKeyColumn={this.state.sortKeyColumn}
                    sortOrder={this.state.sortOrder}
                    onClick={this.selectSortColumn.bind(this)}
                />
                <tbody>
                    {data.map((row) =>
                        <TableDataRow
                            linkParam={`${tableName}_id=${row.id}`}
                            rowName={row.name}
                            row_data={row}
                        />)}
                </tbody>
            </table>
        );
    }

    /**
     * ソートされたデータを得る
     * @return ソートされたデータ
     */
    protected get_sorted_data() {
        const sortedData = this.props.data.slice();

        const col = this.state.sortKeyColumn;
        const order = this.state.sortOrder;

        sortedData.sort((a, b) =>
            (order === (SortOrder.Ascend) ?
                a[col] - b[col] : b[col] - a[col]));

        return sortedData;
    }

    /**
     * ソートするカラムを選択する
     * @param column ソートするカラムの名称
     */
    protected selectSortColumn(column: string) {
        let newSortOrder = SortOrder.Descend;
        if (this.state.sortKeyColumn === column) {
            newSortOrder =
                (this.state.sortOrder === SortOrder.Ascend) ?
                    SortOrder.Descend : SortOrder.Ascend;
        }
        this.setState({
            sortKeyColumn: column,
            sortOrder: newSortOrder,
        });
    }
}

/**
 * 人気データテーブルコンポーネント(魔法領域)
 */
// tslint:disable-next-line:max-classes-per-file
class PopuralityRealmTable extends PopuralityTable {
    public render() {
        if (!this.props.visible) {
            return "";
        }

        const data = this.get_sorted_data();
        const realm = this.props.name;
        return (
            <table className="score statistics_table one_row">
                <TableHeader
                    tableName={this.props.data[0].class_name}
                    sortKeyColumn={this.state.sortKeyColumn}
                    sortOrder={this.state.sortOrder}
                    onClick={this.selectSortColumn.bind(this)}
                />
                <tbody>
                    {data.map((row) =>
                        <TableDataRow
                            linkParam={`class_id=${row.class_id}&${realm}_id=${row.realm_id}`}
                            rowName={row.realm_name}
                            row_data={row}
                        />)}
                </tbody>
            </table>
        );
    }
}

/**
 * 人気データテーブル全体表示コンポーネントプロパティ
 */
interface IRankingTablesProps {
    /** 表示選択中のテーブル名称 */
    selectedTableName: string;
    /** テーブルデータ */
    data: { [col: string]: any[] };
}

/**
 * 人気データテーブル全体表示コンポーネント
 */
// tslint:disable-next-line:max-classes-per-file
class RankingTables extends React.Component<IRankingTablesProps> {
    public shouldComponentUpdate(nextProps: IRankingTablesProps) {
        return (this.props.data === null && nextProps.data !== null) ||
            (this.props.selectedTableName !== nextProps.selectedTableName);
    }

    public render() {
        const tables = Object.keys(name2j).map((name) => {
            if (!name.startsWith("realm")) {
                return (
                    <div id={name} key={name}>
                        <PopuralityTable
                            data={this.props.data[name]}
                            visible={this.props.selectedTableName === name}
                            name={name} />
                    </div>
                );
            } else {
                const realmTables = this.props.data[name].map((i) => {
                    return <PopuralityRealmTable
                        data={i}
                        name={name}
                        visible={this.props.selectedTableName === name}
                        key={i[0].class_id} />;
                });
                return <div id={name} key={name}>{realmTables}</div>;
            }
        });
        return <div>{tables}</div>;
    }
}

/**
 * 人気データ表示コンポーネントプロパティ
 */
interface IPopuralityRankingProps {
    /** 人気データ取得URL */
    dataUrl: string;
}

/**
 * 人気データ表示コンポーネントステータス
 */
interface IPopuralityRankingState {
    /** 表示選択中テーブル名称 */
    selectedTableName: string;
    /** データ内容 */
    data: { [col: string]: any[] };
    /** データ表示状態を示すメッセージ */
    stateMessage: string;
}

/**
 * 人気データ表示コンポーネント
 */
// tslint:disable-next-line:max-classes-per-file
class PopuralityRanking extends React.Component<IPopuralityRankingProps, IPopuralityRankingState> {
    constructor(props: IPopuralityRankingProps) {
        super(props);
        const selectedTableName = this.storageAvailable("sessionStorage") ?
            sessionStorage.getItem("selectedTableName") : null;

        this.state = {
            data: {},
            selectedTableName: selectedTableName !== null ? selectedTableName : "race",
            stateMessage: "Loading...",
        };
    }

    public componentDidMount() {
        fetch(this.props.dataUrl).then((response) => {
            if (!response.ok) {
                throw Error(`${response.status} ${response.statusText}`);
            }
            return response.json();
        }).then((data) => {
            data.realm1 = this.group_by_class(data.realm1);
            data.realm2 = this.group_by_class(data.realm2);
            this.setState({
                data,
                stateMessage: "Success",
            });
        }).catch((err) => {
            this.setState({ stateMessage: err.message });
        });
    }

    public componentDidUpdate() {
        if (this.storageAvailable("sessionStorage")) {
            sessionStorage.setItem("selectedTableName", this.state.selectedTableName);
        }
    }

    public render() {
        if (Object.keys(this.state.data).length === 0) {
            return <div>{this.state.stateMessage}</div>;
        }

        return (
            <div>
                <TableSelector
                    onSelectChange={this.selectTable.bind(this)}
                    selectedTableName={this.state.selectedTableName}
                />
                <RankingTables
                    data={this.state.data}
                    selectedTableName={this.state.selectedTableName}
                />
            </div>
        );
    }

    private storageAvailable(type: string) {
        const storage = (window as any)[type];
        try {
            const x = "__storage_test__";
            storage.setItem(x, x);
            storage.removeItem(x);
            return true;
        } catch (e) {
            return e instanceof DOMException && (
                // everything except Firefox
                e.code === 22 ||
                // Firefox
                e.code === 1014 ||
                // test name field too, because code might not be present
                // everything except Firefox
                e.name === "QuotaExceededError" ||
                // Firefox
                e.name === "NS_ERROR_DOM_QUOTA_REACHED") &&
                // acknowledge QuotaExceededError only if there's something already stored
                storage.length !== 0;
        }
    }

    private selectTable(tableName: string) {
        this.setState({ selectedTableName: tableName });
    }

    private group_by_class(data: any[]) {
        const result: any[] = [];
        data.forEach((i) => {
            if (result[i.class_id] === undefined) {
                result[i.class_id] = [];
            }
            result[i.class_id].push(i);
        });
        return result.filter((e) => e.length > 1);
    }
}

ReactDOM.render(
    <PopuralityRanking dataUrl="get_popularity_ranking.php" />,
    document.getElementById("content"),
);
